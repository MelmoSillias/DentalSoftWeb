<?php

namespace App\Communication\Application\Service;

use App\Communication\Domain\Entity\ShowcaseMessage;
use App\Settings\Domain\Entity\AppSetting;
use App\Settings\Application\Service\InternetFeaturesGate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Communication\Infrastructure\Integration\MailboxImapConnection;
use App\Communication\Infrastructure\Integration\MailboxTlsStream;
use App\Communication\Infrastructure\Security\MailboxCredentialCipher;
use App\Communication\Infrastructure\Security\MailboxHostPolicy;
use App\Communication\Infrastructure\Security\VitrineVoter;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;

final class ShowcaseMailbox
{
    private const SUBJECT = 'Nouvelle demande de rendez-vous';

    public function __construct(private EntityManagerInterface $em, private MailboxCredentialCipher $crypto,
        private NotificationService $notifications, private InternetFeaturesGate $internet,
        #[Autowire('%kernel.cache_dir%')] private string $cacheDir, private MailboxHostPolicy $hostPolicy) {}

    public function config(bool $secret = false): array
    {
        $value = $this->em->getRepository(AppSetting::class)->findOneBy(['keyName' => 'showcase_mailbox'])?->getValue() ?? [];
        if (!$secret) {
            $value['configured'] = str_starts_with($value['password'] ?? '', 'gcm1:');
            unset($value['password']);
        }
        return $value;
    }

    public function save(#[\SensitiveParameter] array $data): void
    {
        $old = $this->config(true);
        foreach (['host', 'email', 'username', 'password'] as $field) {
            if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > ($field === 'password' ? 4096 : 254))) {
                throw new \InvalidArgumentException('Paramètres mail invalides ou trop longs.');
            }
        }
        $host = strtolower(trim($data['host'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $this->hostPolicy->assertAllowedName($host);
        if (!self::validEmail($email)) {
            throw new \InvalidArgumentException('Serveur IMAP ou adresse email invalide.');
        }
        $value = ['host' => $host, 'email' => $email, 'username' => trim((string) ($data['username'] ?? '')) ?: $email];
        if (preg_match('/[\x00-\x1f\x7f]/', $value['username'])) throw new \InvalidArgumentException('Identifiant IMAP invalide.');
        $password = $data['password'] ?? '';
        if ($password === '' && $this->credentialContext($old) !== $this->credentialContext($value)) {
            throw new \InvalidArgumentException('Ressaisissez le mot de passe lors d’un changement de serveur ou de compte.');
        }
        $value['password'] = $password !== '' ? $this->crypto->encrypt($password, $this->credentialContext($value)) : ($old['password'] ?? '');
        if (!$value['password']) { throw new \InvalidArgumentException('Mot de passe requis.'); }
        $connection = $this->connect($value);
        $connection->disconnect();
        $entry = $this->em->getRepository(AppSetting::class)->findOneBy(['keyName' => 'showcase_mailbox']) ?? (new AppSetting())->setKeyName('showcase_mailbox');
        $entry->setValue($value);
        $this->em->persist($entry);
        $this->em->flush();
    }

    private function connect(array $config): MailboxImapConnection
    {
        if (!$this->internet->isEnabled()) { throw new \RuntimeException($this->internet->disabledMessage()); }
        if (empty($config['host'])) { throw new \RuntimeException('Connectez d’abord l’email de contact.'); }
        $addresses = $this->hostPolicy->resolvePublicAddresses($config['host']);
        $password = $this->crypto->decrypt($config['password'], $this->credentialContext($config));
        if (preg_match('/[\x00-\x1f\x7f]/', $password)) { throw new \InvalidArgumentException('Mot de passe IMAP invalide.'); }
        $connection = new MailboxImapConnection(new MailboxTlsStream($addresses));
        try {
            $connection->connect($config['host'], 993, ['encryption' => 'tls', 'timeout' => 10]);
            $connection->login($config['username'], $password);
            $connection->examine('INBOX');
        } catch (\Throwable) {
            $connection->disconnect();
            throw new \RuntimeException('Connexion IMAP refusée. Vérifiez le serveur et les identifiants.');
        }
        return $connection;
    }

    private function credentialContext(array $config): string
    {
        return json_encode([$config['host'] ?? '', $config['username'] ?? '', $config['email'] ?? ''], JSON_THROW_ON_ERROR);
    }


    public static function validEmail(string $value): bool
    {
        return strlen($value) <= 254 && !preg_match('/[\x00-\x20\x7f]/', $value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function utf8(string $text, string $charset): string
    {
        try { return mb_convert_encoding($text, 'UTF-8', $charset === 'default' ? 'UTF-8' : $charset); }
        catch (\ValueError) { return mb_convert_encoding($text, 'UTF-8', 'UTF-8'); }
    }

    public static function isAppointmentSubject(string $subject): bool
    {
        return mb_stripos($subject, self::SUBJECT) !== false;
    }

    private function textBody(MailboxImapConnection $connection, int $uid, ListData $part, string $wanted, string $number = '', int $depth = 0): string
    {
        if ($depth > 12) { return ''; }
        $first = $part->tokenAt(0);
        if ($first instanceof ListData) {
            $children = 0;
            while ($part->tokenAt($children) instanceof ListData) { ++$children; }
            // Multipart extensions follow the children, subtype and parameters.
            if (self::isAttachment($part->tokenAt($children + 2))) { return ''; }
            for ($i = 0; $i < min($children, 50); ++$i) {
                $section = $number === '' ? (string) ($i + 1) : $number.'.'.($i + 1);
                $text = $this->textBody($connection, $uid, $part->tokenAt($i), $wanted, $section, $depth + 1);
                if ($text !== '') { return $text; }
            }
            return '';
        }
        if (strtoupper(MailboxImapConnection::value($first)) !== 'TEXT'
            || strtoupper(MailboxImapConnection::value($part->tokenAt(1))) !== $wanted
            || self::isAttachment($part->tokenAt(9))) { return ''; }

        $text = $connection->readTextPart($uid, $number === '' ? 'TEXT' : $number);
        $text = match (strtoupper(MailboxImapConnection::value($part->tokenAt(5)))) {
            'BASE64' => base64_decode($text) ?: '',
            'QUOTED-PRINTABLE' => quoted_printable_decode($text),
            default => $text,
        };
        $params = $part->tokenAt(2);
        $charset = $params instanceof ListData ? ($params->toKeyValuePairs()['charset'] ?? '') : '';
        $text = $this->utf8($text, $charset !== '' ? $charset : 'UTF-8');
        if ($wanted === 'HTML') {
            $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text) ?? '';
            $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|div|tr)>|<br\s*\/?>/i', "\n", $text) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return mb_substr($text, 0, 100000, 'UTF-8');
    }

    private static function isAttachment(mixed $disposition): bool
    {
        return $disposition instanceof ListData && strtoupper(MailboxImapConnection::value($disposition->tokenAt(0))) === 'ATTACHMENT';
    }

    private function readMailbox(callable $read): mixed
    {
        try { return $read(); }
        catch (\Throwable) { throw new \RuntimeException('Impossible de lire la boîte mail.'); }
    }

    public function sync(): int
    {
        $lock = fopen($this->cacheDir.'/showcase-mailbox.lock', 'c');
        if (!$lock) { throw new \RuntimeException('Impossible de verrouiller la synchronisation.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new \RuntimeException('Une synchronisation est déjà en cours.'); }
        try { return $this->import(); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function import(): int
    {
        $config = $this->config(true);
        $connection = $this->connect($config);
        $count = 0;
        try {
            $validity = $this->readMailbox($connection->uidValidity(...));
            $cursorKey = 'showcase_cursor_'.substr(hash('sha256', $this->credentialContext($config).'|'.$validity), 0, 64);
            $checkpoint = $this->em->getRepository(AppSetting::class)->findOneBy(['keyName' => $cursorKey]) ?? (new AppSetting())->setKeyName($cursorKey);
            $lastUid = (int) ($checkpoint->getValue()['uid'] ?? 0);
            $cursor = $lastUid;
            $uids = $this->readMailbox(fn () => $connection->searchAfter($lastUid, self::SUBJECT));
            $processed = 0;
            $deadline = microtime(true) + 45;
            foreach ($uids as $uid) {
                if ($uid <= $lastUid) continue; // IMAP n:* may include n-1 when the mailbox has no new messages.
                if ($processed >= 50 || microtime(true) >= $deadline) break;
                $cursor = $uid;
                ++$processed;
                $key = hash('sha256', $config['host'].'|'.$config['username'].'|'.$validity.'|'.$uid);
                if ($this->em->getRepository(ShowcaseMessage::class)->findOneBy(['externalId' => $key])) { continue; }
                $metadata = $this->readMailbox(fn () => $connection->messageMetadata($uid));
                if ($metadata === null || !self::isAppointmentSubject($metadata['subject']) || !self::validEmail($metadata['sender'])) { continue; }
                $message = new ShowcaseMessage();
                $message->externalId = $key;
                $message->sender = $metadata['sender'];
                $message->subject = mb_substr($this->utf8($metadata['subject'], 'UTF-8'), 0, 500, 'UTF-8');
                $structure = $metadata['structure'];
                $message->body = $this->readMailbox(fn () => $this->textBody($connection, $uid, $structure, 'PLAIN')
                    ?: $this->textBody($connection, $uid, $structure, 'HTML'));
                $message->receivedAt = $metadata['receivedAt'];
                $this->em->persist($message);
                $this->em->flush();
                $this->notifications->notifyRoles(VitrineVoter::ROLES, 'Nouvelle demande de rendez-vous reçue.', '/vitrine/messagerie?message='.$message->id);
                ++$count;
            }
            if ($cursor > $lastUid) {
                $checkpoint->setValue(['uid' => $cursor]);
                $this->em->persist($checkpoint);
                $this->em->flush();
            }
        } finally { $connection->disconnect(); }
        return $count;
    }

    public function sendEmail(ShowcaseMessage $message, string $body): void
    {
        if (!$this->internet->isEnabled()) { throw new \RuntimeException($this->internet->disabledMessage()); }
        $from = $this->config()['email'] ?? '';
        if (!self::validEmail($from) || !self::validEmail($message->sender)) { throw new \RuntimeException('Adresse email de réponse invalide.'); }
        $subject = mb_encode_mimeheader('Re: '.preg_replace('/[\x00-\x1f\x7f]/', '', $message->subject), 'UTF-8');
        if (!mail($message->sender, $subject, $body, ['From' => $from, 'Reply-To' => $from, 'Content-Type' => 'text/plain; charset=UTF-8'])) {
            throw new \RuntimeException('Le serveur email a refusé l’envoi. Vérifiez la configuration du transport PHP mail.');
        }
    }
}

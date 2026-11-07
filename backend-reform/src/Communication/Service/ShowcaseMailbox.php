<?php

namespace App\Communication\Service;

use App\Communication\Entity\ShowcaseMessage;
use App\Settings\Entity\AppSetting;
use App\Settings\Service\InternetFeaturesGate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Communication\Security\MailboxCredentialCipher;
use App\Communication\Security\MailboxHostPolicy;
use App\Communication\Security\VitrineVoter;

final class ShowcaseMailbox
{
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
        imap_close($connection);
        $entry = $this->em->getRepository(AppSetting::class)->findOneBy(['keyName' => 'showcase_mailbox']) ?? (new AppSetting())->setKeyName('showcase_mailbox');
        $entry->setValue($value);
        $this->em->persist($entry);
        $this->em->flush();
    }

    private function connect(array $config): mixed
    {
        if (!$this->internet->isEnabled()) { throw new \RuntimeException($this->internet->disabledMessage()); }
        if (!function_exists('imap_open')) { throw new \RuntimeException('Activez l’extension PHP IMAP sur le serveur.'); }
        if (empty($config['host'])) { throw new \RuntimeException('Connectez d’abord l’email de contact.'); }
        $this->hostPolicy->assertConnectable($config['host']);
        $password = $this->crypto->decrypt($config['password'], $this->credentialContext($config));
        imap_timeout(IMAP_OPENTIMEOUT, 10);
        imap_timeout(IMAP_READTIMEOUT, 15);
        $connection = @imap_open($this->mailboxName($config), $config['username'], $password, OP_READONLY, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        imap_errors(); // Drain protocol errors; never expose them or authentication details to the client.
        imap_alerts();
        if (!$connection) { throw new \RuntimeException('Connexion IMAP refusée. Vérifiez le serveur et les identifiants.'); }
        return $connection;
    }

    private function credentialContext(array $config): string
    {
        return json_encode([$config['host'] ?? '', $config['username'] ?? '', $config['email'] ?? ''], JSON_THROW_ON_ERROR);
    }

    private function mailboxName(array $config): string { return '{'.$config['host'].':993/imap/ssl/validate-cert/norsh}INBOX'; }

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
        return mb_stripos($subject, 'Nouvelle demande de rendez-vous') !== false;
    }

    private function decodeHeader(string $header): string
    {
        $result = '';
        foreach (imap_mime_header_decode($header) as $part) {
            $result .= $this->utf8($part->text, $part->charset);
        }
        return $result;
    }

    private function textBody(mixed $connection, int $uid, object $part, string $number = '', bool $allowHtml = false, int $depth = 0): string
    {
        if ($depth > 12) return '';
        if (!empty($part->parts)) {
            foreach (array_slice($part->parts, 0, 50) as $i => $child) {
                $text = $this->textBody($connection, $uid, $child, $number === '' ? (string) ($i + 1) : $number.'.'.($i + 1), $allowHtml, $depth + 1);
                if ($text !== '') { return $text; }
            }
            return '';
        }
        $subtype = strtoupper($part->subtype ?? '');
        if ($part->type !== 0 || !in_array($subtype, $allowHtml ? ['PLAIN', 'HTML'] : ['PLAIN'], true) || strtoupper($part->disposition ?? '') === 'ATTACHMENT') { return ''; }
        $text = $number === '' ? imap_body($connection, $uid, FT_UID | FT_PEEK) : imap_fetchbody($connection, $uid, $number, FT_UID | FT_PEEK);
        $text = match ($part->encoding) { 3 => base64_decode($text), 4 => quoted_printable_decode($text), default => $text };
        foreach ($part->parameters ?? [] as $parameter) {
            if (strtolower($parameter->attribute) === 'charset') { $text = $this->utf8($text ?: '', $parameter->value); }
        }
        if ($subtype === 'HTML') {
            $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text ?: '');
            $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|div|tr)>|<br\s*\/?>/i', "\n", $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return mb_substr($this->utf8($text ?: '', 'UTF-8'), 0, 100000);
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
            $mailboxStatus = imap_status($connection, $this->mailboxName($config), SA_UIDVALIDITY);
            if (!$mailboxStatus) throw new \RuntimeException('Impossible de lire l’état de la boîte mail.');
            $validity = $mailboxStatus->uidvalidity;
            $cursorKey = 'showcase_cursor_'.substr(hash('sha256', $this->credentialContext($config).'|'.$validity), 0, 64);
            $checkpoint = $this->em->getRepository(AppSetting::class)->findOneBy(['keyName' => $cursorKey]) ?? (new AppSetting())->setKeyName($cursorKey);
            $lastUid = (int) ($checkpoint->getValue()['uid'] ?? 0);
            $cursor = $lastUid;
            $uids = imap_search($connection, 'UID '.($lastUid + 1).':* SUBJECT "Nouvelle demande de rendez-vous"', SE_UID, 'UTF-8') ?: [];
            sort($uids, SORT_NUMERIC);
            $processed = 0;
            $deadline = microtime(true) + 45;
            foreach ($uids as $uid) {
                if ($uid <= $lastUid) continue; // IMAP n:* may include n-1 when the mailbox has no new messages.
                if ($processed >= 50 || microtime(true) >= $deadline) break;
                $cursor = $uid;
                ++$processed;
                $key = hash('sha256', $config['host'].'|'.$config['username'].'|'.$validity.'|'.$uid);
                if ($this->em->getRepository(ShowcaseMessage::class)->findOneBy(['externalId' => $key])) { continue; }
                $overview = imap_fetch_overview($connection, (string) $uid, FT_UID)[0] ?? null;
                if (!$overview) { continue; }
                if (($overview->size ?? PHP_INT_MAX) > 2 * 1024 * 1024) continue;
                $subject = $this->decodeHeader($overview->subject ?? '');
                if (!self::isAppointmentSubject($subject)) { continue; }
                $message = new ShowcaseMessage();
                $message->externalId = $key;
                $header = imap_headerinfo($connection, imap_msgno($connection, $uid));
                // Website forms generally send from the cabinet and put the patient in Reply-To.
                $address = $header->reply_to[0] ?? $header->from[0] ?? null;
                $message->sender = $address ? $address->mailbox.'@'.$address->host : '';
                if (!self::validEmail($message->sender)) { continue; }
                $message->subject = mb_substr($subject, 0, 500);
                $structure = imap_fetchstructure($connection, $uid, FT_UID);
                if (!$structure) { continue; }
                $message->body = $this->textBody($connection, $uid, $structure);
                if ($message->body === '') { $message->body = $this->textBody($connection, $uid, $structure, allowHtml: true); }
                $message->receivedAt = new \DateTimeImmutable('@'.($overview->udate ?? time()));
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
        } finally { imap_close($connection); imap_errors(); imap_alerts(); }
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

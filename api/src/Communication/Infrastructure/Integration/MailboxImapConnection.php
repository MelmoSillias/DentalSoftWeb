<?php

namespace App\Communication\Infrastructure\Integration;

use DirectoryTree\ImapEngine\Connection\ImapConnection;
use DirectoryTree\ImapEngine\Connection\ImapParser;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ResponseCodeData;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Tokens\Literal;
use DirectoryTree\ImapEngine\Connection\Tokens\Nil;
use DirectoryTree\ImapEngine\Connection\Tokens\QuotedString;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Exceptions\ImapParserException;
use DirectoryTree\ImapEngine\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class MailboxImapConnection extends ImapConnection
{
    public const MAX_MESSAGE_BYTES = 2 * 1024 * 1024;
    private int $responseLimit = 262144;

    public function send(string $name, array $tokens = [], ?string &$tag = null): void
    {
        if ($this->stream instanceof MailboxTlsStream) {
            $this->stream->limitResponse($this->responseLimit);
        }
        parent::send($name, $tokens, $tag);
    }

    protected function newParser(StreamInterface $stream): ImapParser
    {
        return new class($this->newTokenizer($stream)) extends ImapParser {
            private int $depth = 0;

            protected function parseList(): ListData
            {
                return $this->bounded(fn () => parent::parseList());
            }

            protected function parseBracketGroup(): ResponseCodeData
            {
                return $this->bounded(fn () => parent::parseBracketGroup());
            }

            private function bounded(callable $parse): ListData|ResponseCodeData
            {
                if (++$this->depth > 64) { throw new ImapParserException('Réponse IMAP trop imbriquée.'); }
                try { return $parse(); }
                finally { --$this->depth; }
            }
        };
    }

    public function uidValidity(): int
    {
        $data = $this->status('INBOX', ['UIDVALIDITY'])->tokenAt(3);
        $uidValidity = $data instanceof ListData ? self::positiveInt($data->lookup('UIDVALIDITY')) : null;
        return $uidValidity ?? throw new \RuntimeException('Impossible de lire l’état de la boîte mail.');
    }

    /** @return list<int> */
    public function searchAfter(int $lastUid, string $subject): array
    {
        if ($lastUid >= 4294967295) { return []; }
        $response = $this->search(['UID', ($lastUid + 1).':*', 'SUBJECT', Str::literal($subject)]);
        $uids = [];
        foreach ($response->data() as $token) {
            $uid = self::positiveInt($token);
            if ($uid !== null && $uid > $lastUid) { $uids[$uid] = $uid; }
        }
        sort($uids, SORT_NUMERIC);
        return $uids;
    }

    /** @return array{subject: string, sender: string, receivedAt: \DateTimeImmutable, structure: ListData}|null */
    public function messageMetadata(int $uid): ?array
    {
        $data = $this->fetchData($uid, ['RFC822.SIZE', 'INTERNALDATE', 'ENVELOPE', 'BODYSTRUCTURE']);
        $size = self::positiveInt($data?->lookup('RFC822.SIZE'));
        $envelope = $data?->lookup('ENVELOPE');
        $structure = $data?->lookup('BODYSTRUCTURE');
        if ($size === null || $size > self::MAX_MESSAGE_BYTES || !$envelope instanceof ListData || !$structure instanceof ListData) {
            return null;
        }
        $subject = self::value($envelope->tokenAt(1));
        $date = self::value($data->lookup('INTERNALDATE'));
        $receivedAt = \DateTimeImmutable::createFromFormat('!j-M-Y H:i:s O', trim($date));
        return [
            'subject' => Str::decodeMimeHeader($subject),
            'sender' => self::firstAddress($envelope->tokenAt(4)) ?: self::firstAddress($envelope->tokenAt(2)),
            'receivedAt' => ($receivedAt ?: new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC')),
            'structure' => $structure,
        ];
    }

    public function readTextPart(int $uid, string $section): string
    {
        $this->responseLimit = self::MAX_MESSAGE_BYTES + 65536;
        try {
            $data = $this->fetchData($uid, ['BODY.PEEK['.$section.']<0.'.(self::MAX_MESSAGE_BYTES + 1).'>']);
        } finally {
            $this->responseLimit = 262144;
        }
        $tokens = $data?->tokens() ?? [];
        foreach ($tokens as $i => $token) {
            if (!$token instanceof Token || !$token->is('BODY')) { continue; }
            $part = $tokens[$i + 1] ?? null;
            if (!$part instanceof ResponseCodeData || self::value($part->tokenAt(0)) !== $section) { continue; }
            $literal = $tokens[$i + 3] ?? null;
            if (self::value($tokens[$i + 2] ?? null) === '0' && ($literal instanceof Literal || $literal instanceof QuotedString)) {
                return strlen($literal->value) <= self::MAX_MESSAGE_BYTES ? $literal->value : '';
            }
        }
        return '';
    }

    private function fetchData(int $uid, array $items): ?ListData
    {
        foreach ($this->fetch(['UID', ...$items], $uid) as $response) {
            $list = $response->tokenAt(3);
            if ($list instanceof ListData && self::positiveInt($list->lookup('UID')) === $uid) {
                return $list;
            }
        }
        return null;
    }

    public static function value(mixed $token): string
    {
        return $token instanceof Token && !$token instanceof Nil ? $token->value : '';
    }

    private static function positiveInt(mixed $token): ?int
    {
        $value = self::value($token);
        return ctype_digit($value) && (int) $value > 0 && (int) $value <= 4294967295 ? (int) $value : null;
    }

    private static function firstAddress(mixed $list): string
    {
        $address = $list instanceof ListData ? $list->tokenAt(0) : null;
        $mailbox = $address instanceof ListData ? self::value($address->tokenAt(2)) : '';
        $host = $address instanceof ListData ? self::value($address->tokenAt(3)) : '';
        return $mailbox !== '' && $host !== '' ? $mailbox.'@'.$host : '';
    }
}

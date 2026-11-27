<?php

namespace App\Tests\Communication;

use App\Communication\Infrastructure\Integration\MailboxImapConnection;
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;
use DirectoryTree\ImapEngine\Exceptions\ImapParserException;
use PHPUnit\Framework\TestCase;

final class MailboxImapConnectionTest extends TestCase
{
    public function testReadOnlySessionUsesUidMetadataThenPeeksAtTheTextPart(): void
    {
        $stream = (new FakeStream())->feed([
            '* OK IMAP ready',
            'TAG1 OK Logged in',
            '* 3 EXISTS',
            'TAG2 OK [READ-ONLY] INBOX examined',
            '* STATUS INBOX (UIDVALIDITY 12345)',
            'TAG3 OK status completed',
            '* SEARCH 32 30 31 32',
            'TAG4 OK search completed',
            '* 1 FETCH (RFC822.SIZE 2097153 UID 31)',
            'TAG5 OK oversized metadata',
            '* 2 FETCH (UID 32 RFC822.SIZE 500 INTERNALDATE " 7-Nov-2026 10:00:00 +0200" ENVELOPE (NIL "=?UTF-8?Q?Nouvelle_demande_de_rendez-vous?=" ((NIL NIL "cabinet" "example.org")) NIL ((NIL NIL "patient" "example.org")) NIL NIL NIL NIL NIL) BODYSTRUCTURE ("TEXT" "PLAIN" ("CHARSET" "UTF-8") NIL NIL "7BIT" 5 1))',
            'TAG6 OK metadata completed',
            '* 2 FETCH (BODY[TEXT]<0> {5}',
            'Hello UID 32)',
            'TAG7 OK text completed',
        ]);
        $imap = new MailboxImapConnection($stream);
        try {
            $imap->connect('imap.example.org', 993, ['encryption' => 'tls']);
            $imap->login('contact@example.org', 'test-password');
            $imap->examine();
            self::assertSame(12345, $imap->uidValidity());
            self::assertSame([31, 32], $imap->searchAfter(30, 'Nouvelle demande de rendez-vous'));
            self::assertNull($imap->messageMetadata(31));
            $metadata = $imap->messageMetadata(32);
            self::assertNotNull($metadata);
            self::assertSame('Nouvelle demande de rendez-vous', $metadata['subject']);
            self::assertSame('patient@example.org', $metadata['sender']);
            self::assertSame('2026-11-07T08:00:00+00:00', $metadata['receivedAt']->format(DATE_ATOM));
            self::assertSame('PLAIN', MailboxImapConnection::value($metadata['structure']->tokenAt(1)));
            self::assertSame('Hello', $imap->readTextPart(32, 'TEXT'));
            $stream->assertWritten('TAG2 EXAMINE "INBOX"');
            $stream->assertWritten('TAG4 UID SEARCH UID 31:* SUBJECT "Nouvelle demande de rendez-vous"');
            $stream->assertWritten('TAG7 UID FETCH 32 (UID BODY.PEEK[TEXT]<0.2097153>)');
            $stream->assertNotWritten('BODY.PEEK');
        } finally {
            $imap->disconnect();
        }
    }

    public function testRejectsExcessivelyNestedProtocolData(): void
    {
        $stream = (new FakeStream())->feed(['* OK ready', '* STATUS INBOX '.str_repeat('(', 65).'1'.str_repeat(')', 65), 'TAG1 OK']);
        $imap = new MailboxImapConnection($stream);
        try {
            $imap->connect('imap.example.org', 993, ['encryption' => 'tls']);
            $this->expectException(ImapParserException::class);
            $imap->uidValidity();
        } finally {
            $imap->disconnect();
        }
    }
}

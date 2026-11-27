<?php

namespace App\Communication\Infrastructure\Integration;

use DirectoryTree\ImapEngine\Connection\Streams\ImapStream;
use DirectoryTree\ImapEngine\Exceptions\ImapStreamException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class MailboxTlsStream extends ImapStream
{
    private int $remaining = 0;
    private float $deadline = 0;

    /** @param list<string> $addresses Already validated by MailboxHostPolicy. */
    public function __construct(private readonly array $addresses) {}

    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
    {
        // Connect to the checked IP, but verify the original DNS name (also used for SNI).
        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);
        $deadline = hrtime(true) / 1e9 + $timeout;
        foreach ($this->addresses as $ip) {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) { break; }
            $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            $this->stream = @stream_socket_client('tls://'.$address.':993', $errno, $error, $remaining, STREAM_CLIENT_CONNECT, $context);
            if ($this->opened()) {
                $this->limitResponse(262144);
                return true;
            }
        }
        throw new ImapStreamException('Connexion TLS IMAP impossible.');
    }

    public function limitResponse(int $bytes): void
    {
        $this->remaining = $bytes;
        $this->deadline = hrtime(true) / 1e9 + 15;
    }

    public function fgets(): string|false
    {
        $this->prepareRead();
        $line = @fgets($this->stream, min($this->remaining, 65536) + 1);
        if ($line !== false && !str_ends_with($line, "\n")) {
            throw new ImapStreamException('Ligne IMAP tronquée ou trop longue.');
        }
        $this->remaining -= $line === false ? 0 : strlen($line);
        return $line;
    }

    public function read(int $length): string|false
    {
        if ($length < 0 || $length > $this->remaining) {
            throw new ImapStreamException('Réponse IMAP trop volumineuse.');
        }
        $data = '';
        while (strlen($data) < $length) {
            $this->prepareRead();
            $chunk = @fread($this->stream, min(8192, $length - strlen($data)));
            if ($chunk === false || $chunk === '') { return false; }
            $this->remaining -= strlen($chunk);
            $data .= $chunk;
        }
        return $data;
    }

    public function fwrite(string $data): int|false
    {
        $this->prepareRead();
        $written = @fwrite($this->stream, $data);
        return $written === strlen($data) ? $written : false;
    }

    private function prepareRead(): void
    {
        $microseconds = (int) (($this->deadline - hrtime(true) / 1e9) * 1e6);
        if (!$this->opened() || $microseconds <= 0 || $this->remaining <= 0) {
            throw new ImapStreamException('Limite de lecture IMAP atteinte.');
        }
        stream_set_timeout($this->stream, intdiv($microseconds, 1000000), $microseconds % 1000000);
    }
}

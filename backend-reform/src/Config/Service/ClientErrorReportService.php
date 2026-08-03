<?php

namespace App\Config\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ClientErrorReportService
{
    private const MAX_BODY_BYTES = 4096;

    private const ALLOWED_KEYS = [
        'message',
        'context',
        'source',
        'route',
        'buildId',
        'status',
        'code',
        'stack',
        'userAgent',
    ];

    public function __construct(
        #[Autowire(service: 'monolog.logger.client_error')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function report(array $raw, ?int $userId, ?string $clientIp): void
    {
        $encoded = json_encode($raw, JSON_THROW_ON_ERROR);
        if (\strlen($encoded) > self::MAX_BODY_BYTES) {
            return;
        }

        $payload = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (!\array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if (\is_scalar($value)) {
                $payload[$key] = $this->truncateString((string) $value, $this->maxLengthFor($key));
            }
        }

        if ($payload === []) {
            return;
        }

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        if ($clientIp !== null && $clientIp !== '') {
            $payload['client_ip_hash'] = substr(hash('sha256', $clientIp), 0, 16);
        }

        $this->logger->warning('client_error', $payload);
    }

    private function maxLengthFor(string $key): int
    {
        return match ($key) {
            'stack' => 2000,
            'route', 'message' => 500,
            'context' => 200,
            'userAgent' => 256,
            default => 64,
        };
    }

    private function truncateString(string $value, int $maxLen): string
    {
        if (\strlen($value) <= $maxLen) {
            return $value;
        }

        return substr($value, 0, $maxLen).'…';
    }
}

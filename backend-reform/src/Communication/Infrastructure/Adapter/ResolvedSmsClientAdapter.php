<?php

namespace App\Communication\Infrastructure\Adapter;

use App\Communication\Application\Port\SmsClientPort;
use App\Communication\Service\SmsClientResolver;

/**
 * Bridges Application SmsClientPort to the active provider resolved by legacy SmsClientResolver.
 */
final class ResolvedSmsClientAdapter implements SmsClientPort
{
    public function __construct(private readonly SmsClientResolver $resolver)
    {
    }

    public function testConnection(): array
    {
        return $this->resolver->getClient()->testConnection();
    }

    public function fetchProviderOverview(): array
    {
        return $this->resolver->getClient()->fetchProviderOverview();
    }

    public function sendSms(string $phone, string $message, ?string $senderOverride = null): array
    {
        return $this->resolver->getClient()->sendSms($phone, $message, $senderOverride);
    }
}

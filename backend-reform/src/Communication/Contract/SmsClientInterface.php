<?php

namespace App\Communication\Contract;

interface SmsClientInterface
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchProviderOverview(): array;

    /**
     * @return array{success: bool, providerMessageId?: string|null, error?: string}
     */
    public function sendSms(string $phone, string $message, ?string $senderOverride = null): array;
}

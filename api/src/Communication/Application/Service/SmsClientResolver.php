<?php

namespace App\Communication\Application\Service;

use App\Communication\Infrastructure\Integration\OrangeSmsClient;

use App\Communication\Application\Port\SmsClientInterface;
use App\Communication\Domain\Entity\SmsProviderConfig;

final class SmsClientResolver
{
    public const PROVIDER_ORANGE = 'orange';
    public const PROVIDER_AFRIKSMS = 'afriksms';

    public function __construct(
        private readonly OrangeSmsClient $orangeSmsClient,
        private readonly AfrikSmsClient $afrikSmsClient,
        private readonly SmsConfigService $smsConfigService,
    ) {
    }

    public function getClient(?SmsProviderConfig $config = null): SmsClientInterface
    {
        $config ??= $this->smsConfigService->getConfig();

        return match ($config->getProvider()) {
            self::PROVIDER_AFRIKSMS => $this->afrikSmsClient,
            default => $this->orangeSmsClient,
        };
    }

    public function getAfrikSmsClient(): AfrikSmsClient
    {
        return $this->afrikSmsClient;
    }
}

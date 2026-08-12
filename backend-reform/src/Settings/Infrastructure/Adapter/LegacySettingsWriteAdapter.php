<?php

namespace App\Settings\Infrastructure\Adapter;

use App\Settings\Application\Port\SettingsWritePort;
use App\Settings\Service\GlobalSettingsService;

final class LegacySettingsWriteAdapter implements SettingsWritePort
{
    public function __construct(private readonly GlobalSettingsService $globalSettingsService)
    {
    }

    public function saveGeneralSettings(array $payload): array
    {
        return $this->globalSettingsService->saveGeneralSettings($payload);
    }
}

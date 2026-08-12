<?php

namespace App\Settings\Infrastructure\Adapter;

use App\Settings\Application\Port\SettingsReadPort;
use App\Settings\Service\GlobalSettingsService;

final class LegacySettingsReadAdapter implements SettingsReadPort
{
    public function __construct(private readonly GlobalSettingsService $globalSettingsService)
    {
    }

    public function getGeneralSettings(): array
    {
        return $this->globalSettingsService->getGeneralSettings();
    }
}

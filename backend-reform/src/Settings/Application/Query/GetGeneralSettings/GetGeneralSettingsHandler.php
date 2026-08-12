<?php

namespace App\Settings\Application\Query\GetGeneralSettings;

use App\Settings\Application\Port\SettingsReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetGeneralSettingsHandler implements QueryHandler
{
    public function __construct(private readonly SettingsReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetGeneralSettingsQuery $query): array
    {
        return $this->readPort->getGeneralSettings();
    }
}

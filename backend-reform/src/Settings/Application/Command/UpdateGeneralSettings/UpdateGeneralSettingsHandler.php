<?php

namespace App\Settings\Application\Command\UpdateGeneralSettings;

use App\Settings\Application\Port\SettingsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateGeneralSettingsHandler implements CommandHandler
{
    public function __construct(private readonly SettingsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateGeneralSettingsCommand $command): array
    {
        return $this->writePort->saveGeneralSettings($command->payload);
    }
}

<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheBilans;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFicheBilansHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFicheBilansCommand $command): void
    {
        $this->writePort->updateBilans($command->ficheId, $command->payload);
    }
}

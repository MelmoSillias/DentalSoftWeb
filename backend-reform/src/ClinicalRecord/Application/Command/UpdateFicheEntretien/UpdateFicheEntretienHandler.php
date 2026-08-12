<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheEntretien;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFicheEntretienHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFicheEntretienCommand $command): void
    {
        $this->writePort->updateEntretien($command->ficheId, $command->payload);
    }
}

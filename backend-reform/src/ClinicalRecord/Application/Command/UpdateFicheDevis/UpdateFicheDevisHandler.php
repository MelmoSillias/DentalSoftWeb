<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheDevis;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFicheDevisHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFicheDevisCommand $command): void
    {
        $this->writePort->updateDevis($command->ficheId, $command->payload);
    }
}

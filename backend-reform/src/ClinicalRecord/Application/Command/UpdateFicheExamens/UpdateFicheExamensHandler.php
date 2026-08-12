<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheExamens;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFicheExamensHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFicheExamensCommand $command): void
    {
        $this->writePort->updateExamens($command->ficheId, $command->payload);
    }
}

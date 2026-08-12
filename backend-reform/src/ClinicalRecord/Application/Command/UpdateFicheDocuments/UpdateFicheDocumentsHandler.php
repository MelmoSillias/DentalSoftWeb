<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheDocuments;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFicheDocumentsHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFicheDocumentsCommand $command): void
    {
        $this->writePort->updateDocuments($command->ficheId, $command->payload, $command->files);
    }
}

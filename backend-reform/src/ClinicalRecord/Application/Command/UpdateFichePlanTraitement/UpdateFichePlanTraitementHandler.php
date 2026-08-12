<?php

namespace App\ClinicalRecord\Application\Command\UpdateFichePlanTraitement;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFichePlanTraitementHandler implements CommandHandler
{
    public function __construct(private readonly FicheMedicaleWritePort $writePort)
    {
    }

    public function __invoke(UpdateFichePlanTraitementCommand $command): void
    {
        $this->writePort->updatePlanTraitement($command->ficheId, $command->payload);
    }
}

<?php

namespace App\Patient\Application\Command\UpdatePatientDossier;

use App\Patient\Application\Port\PatientDossierPort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdatePatientDossierHandler implements CommandHandler
{
    public function __construct(private readonly PatientDossierPort $dossierPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdatePatientDossierCommand $command): array
    {
        return $this->dossierPort->updateDossier($command->patientId, $command->payload);
    }
}

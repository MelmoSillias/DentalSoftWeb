<?php

namespace App\Patient\Application\Query\GetPatientDossier;

use App\Patient\Application\Port\PatientDossierPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPatientDossierHandler implements QueryHandler
{
    public function __construct(private readonly PatientDossierPort $dossierPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPatientDossierQuery $query): ?array
    {
        return $this->dossierPort->getDossierData($query->patientId);
    }
}

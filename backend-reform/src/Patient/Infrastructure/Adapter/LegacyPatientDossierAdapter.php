<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientDossierPort;
use App\Patient\Service\PatientService;

final class LegacyPatientDossierAdapter implements PatientDossierPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function getDossierData(int $patientId): ?array
    {
        return $this->patientService->getDossierData($patientId);
    }

    public function updateDossier(int $patientId, array $payload): array
    {
        return $this->patientService->updateDossier($patientId, $payload);
    }
}

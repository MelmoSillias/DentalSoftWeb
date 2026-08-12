<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientConsultationsReadPort;
use App\Patient\Service\PatientService;

final class LegacyPatientConsultationsReadAdapter implements PatientConsultationsReadPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function listPatientConsultations(int $patientId): array
    {
        return $this->patientService->listPatientConsultations($patientId);
    }
}

<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientInsurancePort;
use App\Patient\Service\PatientService;

final class LegacyPatientInsuranceAdapter implements PatientInsurancePort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function applyInsuranceProfile(int $patientId, array $data): void
    {
        $this->patientService->applyInsuranceProfileByPatientId($patientId, $data);
    }
}

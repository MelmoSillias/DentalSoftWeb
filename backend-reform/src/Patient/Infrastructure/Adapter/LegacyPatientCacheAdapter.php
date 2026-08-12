<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Service\PatientService;

final class LegacyPatientCacheAdapter implements PatientCachePort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function clearPatientsCache(): void
    {
        $this->patientService->clearPatientsListCache();
    }
}

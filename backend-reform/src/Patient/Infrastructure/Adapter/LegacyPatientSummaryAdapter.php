<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientSummaryPort;
use App\Patient\Service\PatientService;

final class LegacyPatientSummaryAdapter implements PatientSummaryPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function getPatientSummary(int $patientId): ?array
    {
        return $this->patientService->getFormattedPatientSummary($patientId);
    }
}

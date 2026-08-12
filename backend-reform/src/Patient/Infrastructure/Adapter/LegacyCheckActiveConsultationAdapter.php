<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\CheckActiveConsultationPort;
use App\Patient\Service\PatientService;

final class LegacyCheckActiveConsultationAdapter implements CheckActiveConsultationPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function checkActive(int $patientId): array
    {
        return $this->patientService->checkConsultationActive($patientId);
    }
}

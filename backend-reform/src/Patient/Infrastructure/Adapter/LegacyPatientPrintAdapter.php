<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientPrintPort;
use App\Patient\Service\PatientService;

final class LegacyPatientPrintAdapter implements PatientPrintPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function getPrintInfosPersoContext(int $patientId): ?object
    {
        return $this->patientService->getPrintInfosPersoContext($patientId);
    }

    public function getPrintInfosPersoData(int $patientId): ?array
    {
        return $this->patientService->getPrintInfosPersoData($patientId);
    }

    public function getPrintFicheContext(int $patientId, int $ficheId): ?array
    {
        return $this->patientService->getPrintFicheContext($patientId, $ficheId);
    }

    public function getPrintFicheData(int $patientId, int $ficheId): ?array
    {
        return $this->patientService->getPrintFicheData($patientId, $ficheId);
    }
}

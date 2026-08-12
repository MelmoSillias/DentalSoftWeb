<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientPortalPort;
use App\Patient\Service\PatientService;

final class LegacyPatientPortalAdapter implements PatientPortalPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function getPatientPortalAccountData(int $id): array
    {
        return $this->patientService->getPatientPortalAccountData($id);
    }

    public function createPatientPortalAccount(int $id): array
    {
        return $this->patientService->createPatientPortalAccount($id);
    }

    public function resetPatientPortalPassword(int $id): array
    {
        return $this->patientService->resetPatientPortalPassword($id);
    }

    public function togglePatientPortalAccount(int $id, bool $active): array
    {
        return $this->patientService->togglePatientPortalAccount($id, $active);
    }

    public function createMissingPatientPortalAccounts(): array
    {
        return $this->patientService->createMissingPatientPortalAccounts();
    }
}

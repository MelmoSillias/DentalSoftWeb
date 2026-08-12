<?php

namespace App\Patient\Application\Port;

interface PatientPortalPort
{
    /**
     * @return array<string, mixed>
     */
    public function getPatientPortalAccountData(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function createPatientPortalAccount(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function resetPatientPortalPassword(int $id): array;

    /**
     * @return array<string, mixed>
     */
    public function togglePatientPortalAccount(int $id, bool $active): array;

    /**
     * @return array<string, mixed>
     */
    public function createMissingPatientPortalAccounts(): array;
}

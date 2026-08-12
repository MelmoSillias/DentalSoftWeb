<?php

namespace App\Patient\Application\Port;

interface PatientInsurancePort
{
    /**
     * @param array<string, mixed> $data
     */
    public function applyInsuranceProfile(int $patientId, array $data): void;
}

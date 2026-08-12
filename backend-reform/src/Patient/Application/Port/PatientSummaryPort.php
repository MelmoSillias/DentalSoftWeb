<?php

namespace App\Patient\Application\Port;

/**
 * Read-model formatting for API responses during the transitional cutover.
 */
interface PatientSummaryPort
{
    /**
     * @return array<string, mixed>|null
     */
    public function getPatientSummary(int $patientId): ?array;
}

<?php

namespace App\Patient\Application\Port;

interface PatientRealtimePort
{
    public function publishPatientRefresh(int $patientId, string $action): void;
}

<?php

namespace App\Patient\Application\Port;

interface PatientConsultationsReadPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPatientConsultations(int $patientId): array;
}

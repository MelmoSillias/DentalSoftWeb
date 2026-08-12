<?php

namespace App\Patient\Application\Port;

interface PatientDossierPort
{
    /**
     * @return array<string, mixed>|null
     */
    public function getDossierData(int $patientId): ?array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateDossier(int $patientId, array $payload): array;
}

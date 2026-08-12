<?php

namespace App\Patient\Application\Port;

interface PatientPrintPort
{
    /**
     * Twig context: legacy Patient entity, or null when not found.
     */
    public function getPrintInfosPersoContext(int $patientId): ?object;

    /**
     * @return array<string, mixed>|null
     */
    public function getPrintInfosPersoData(int $patientId): ?array;

    /**
     * Twig context with patient/fiche entities, or null when not found.
     *
     * @return array<string, mixed>|null
     */
    public function getPrintFicheContext(int $patientId, int $ficheId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function getPrintFicheData(int $patientId, int $ficheId): ?array;
}

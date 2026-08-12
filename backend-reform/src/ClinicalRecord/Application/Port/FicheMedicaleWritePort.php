<?php

namespace App\ClinicalRecord\Application\Port;

interface FicheMedicaleWritePort
{
    /**
     * @param array<string, mixed> $data
     */
    public function updateEntretien(int $ficheId, array $data): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateExamens(int $ficheId, array $data): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateBilans(int $ficheId, array $data): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updatePlanTraitement(int $ficheId, array $data): void;

    /**
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $files
     */
    public function updateDocuments(int $ficheId, array $data, array $files = []): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateDevis(int $ficheId, array $data): void;
}

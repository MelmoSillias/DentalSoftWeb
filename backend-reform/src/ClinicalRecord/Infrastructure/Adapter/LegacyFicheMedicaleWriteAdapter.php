<?php

namespace App\ClinicalRecord\Infrastructure\Adapter;

use App\ClinicalRecord\Application\Port\FicheMedicaleWritePort;
use App\ClinicalRecord\Service\FicheMedicaleService;

final class LegacyFicheMedicaleWriteAdapter implements FicheMedicaleWritePort
{
    public function __construct(private readonly FicheMedicaleService $ficheMedicaleService)
    {
    }

    public function updateEntretien(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updateEntretien($ficheId, $data);
    }

    public function updateExamens(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updateExamens($ficheId, $data);
    }

    public function updateBilans(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updateBilans($ficheId, $data);
    }

    public function updatePlanTraitement(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updatePlanTraitement($ficheId, $data);
    }

    public function updateDocuments(int $ficheId, array $data, array $files = []): void
    {
        $this->ficheMedicaleService->updateDocuments($ficheId, $data, $files);
    }

    public function updateDevis(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updateDevis($ficheId, $data);
    }
}

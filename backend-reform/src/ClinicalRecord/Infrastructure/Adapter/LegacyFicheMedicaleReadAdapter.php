<?php

namespace App\ClinicalRecord\Infrastructure\Adapter;

use App\ClinicalRecord\Application\Port\FicheMedicaleReadPort;
use App\ClinicalRecord\Service\FicheMedicaleService;

final class LegacyFicheMedicaleReadAdapter implements FicheMedicaleReadPort
{
    public function __construct(private readonly FicheMedicaleService $ficheMedicaleService)
    {
    }

    public function getFicheJson(int $ficheId): array
    {
        return $this->ficheMedicaleService->getFicheJson($ficheId);
    }
}

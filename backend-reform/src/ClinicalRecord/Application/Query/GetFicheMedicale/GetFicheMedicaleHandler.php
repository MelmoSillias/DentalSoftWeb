<?php

namespace App\ClinicalRecord\Application\Query\GetFicheMedicale;

use App\ClinicalRecord\Application\Port\FicheMedicaleReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFicheMedicaleHandler implements QueryHandler
{
    public function __construct(private readonly FicheMedicaleReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetFicheMedicaleQuery $query): array
    {
        return $this->readPort->getFicheJson($query->ficheId);
    }
}

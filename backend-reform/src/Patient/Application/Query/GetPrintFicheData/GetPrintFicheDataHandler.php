<?php

namespace App\Patient\Application\Query\GetPrintFicheData;

use App\Patient\Application\Port\PatientPrintPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPrintFicheDataHandler implements QueryHandler
{
    public function __construct(private readonly PatientPrintPort $printPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPrintFicheDataQuery $query): ?array
    {
        return $this->printPort->getPrintFicheData($query->patientId, $query->ficheId);
    }
}

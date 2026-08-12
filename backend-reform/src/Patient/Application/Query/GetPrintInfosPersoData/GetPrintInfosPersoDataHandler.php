<?php

namespace App\Patient\Application\Query\GetPrintInfosPersoData;

use App\Patient\Application\Port\PatientPrintPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPrintInfosPersoDataHandler implements QueryHandler
{
    public function __construct(private readonly PatientPrintPort $printPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPrintInfosPersoDataQuery $query): ?array
    {
        return $this->printPort->getPrintInfosPersoData($query->patientId);
    }
}

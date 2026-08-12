<?php

namespace App\Patient\Application\Query\GetPrintInfosPerso;

use App\Patient\Application\Port\PatientPrintPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPrintInfosPersoHandler implements QueryHandler
{
    public function __construct(private readonly PatientPrintPort $printPort)
    {
    }

    public function __invoke(GetPrintInfosPersoQuery $query): ?object
    {
        return $this->printPort->getPrintInfosPersoContext($query->patientId);
    }
}

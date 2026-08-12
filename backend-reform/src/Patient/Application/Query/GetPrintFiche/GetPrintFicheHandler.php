<?php

namespace App\Patient\Application\Query\GetPrintFiche;

use App\Patient\Application\Port\PatientPrintPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPrintFicheHandler implements QueryHandler
{
    public function __construct(private readonly PatientPrintPort $printPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPrintFicheQuery $query): ?array
    {
        return $this->printPort->getPrintFicheContext($query->patientId, $query->ficheId);
    }
}

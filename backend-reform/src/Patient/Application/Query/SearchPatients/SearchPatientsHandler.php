<?php

namespace App\Patient\Application\Query\SearchPatients;

use App\Patient\Application\Port\PatientReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class SearchPatientsHandler implements QueryHandler
{
    public function __construct(private readonly PatientReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(SearchPatientsQuery $query): array
    {
        return $this->readPort->searchPatients($query->term, $query->limit);
    }
}

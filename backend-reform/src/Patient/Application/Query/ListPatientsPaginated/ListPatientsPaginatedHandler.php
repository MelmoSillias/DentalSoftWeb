<?php

namespace App\Patient\Application\Query\ListPatientsPaginated;

use App\Patient\Application\Port\PatientReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListPatientsPaginatedHandler implements QueryHandler
{
    public function __construct(private readonly PatientReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function __invoke(ListPatientsPaginatedQuery $query): array
    {
        return $this->readPort->listPatientsCollection(
            user: $query->user,
            medecinOnly: $query->medecinOnly,
            paginated: $query->paginated,
            page: $query->page,
            limit: $query->limit,
            query: $query->query,
            sortField: $query->sortField,
            sortOrder: $query->sortOrder,
        );
    }
}

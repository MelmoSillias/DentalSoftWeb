<?php

namespace App\Patient\Application\Query\ListDeletedPatients;

use App\Patient\Application\Port\PatientReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListDeletedPatientsHandler implements QueryHandler
{
    public function __construct(private readonly PatientReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListDeletedPatientsQuery $query): array
    {
        return $this->readPort->listDeletedPatientsPaginated(
            $query->page,
            $query->limit,
            $query->query,
        );
    }
}

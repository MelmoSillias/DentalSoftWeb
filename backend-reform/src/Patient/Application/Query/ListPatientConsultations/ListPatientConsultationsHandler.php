<?php

namespace App\Patient\Application\Query\ListPatientConsultations;

use App\Patient\Application\Port\PatientConsultationsReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListPatientConsultationsHandler implements QueryHandler
{
    public function __construct(private readonly PatientConsultationsReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListPatientConsultationsQuery $query): array
    {
        return $this->readPort->listPatientConsultations($query->patientId);
    }
}

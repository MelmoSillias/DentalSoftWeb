<?php

namespace App\Patient\Application\Query\GetPatientDetails;

use App\Patient\Application\Port\PatientReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPatientDetailsHandler implements QueryHandler
{
    public function __construct(private readonly PatientReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPatientDetailsQuery $query): ?array
    {
        return $this->readPort->getPatientDetailsData($query->patientId);
    }
}

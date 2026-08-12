<?php

namespace App\Patient\Application\Query\GetPatientPortalAccount;

use App\Patient\Application\Port\PatientPortalPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPatientPortalAccountHandler implements QueryHandler
{
    public function __construct(private readonly PatientPortalPort $portalPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetPatientPortalAccountQuery $query): array
    {
        return $this->portalPort->getPatientPortalAccountData($query->patientId);
    }
}

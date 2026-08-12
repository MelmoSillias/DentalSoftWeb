<?php

namespace App\Patient\Application\Query\GetPatientPortalAccount;

final class GetPatientPortalAccountQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

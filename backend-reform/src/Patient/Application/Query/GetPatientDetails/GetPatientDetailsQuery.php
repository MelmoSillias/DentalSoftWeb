<?php

namespace App\Patient\Application\Query\GetPatientDetails;

final class GetPatientDetailsQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

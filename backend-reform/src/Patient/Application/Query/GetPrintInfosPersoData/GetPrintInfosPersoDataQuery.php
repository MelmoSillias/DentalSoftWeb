<?php

namespace App\Patient\Application\Query\GetPrintInfosPersoData;

final class GetPrintInfosPersoDataQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

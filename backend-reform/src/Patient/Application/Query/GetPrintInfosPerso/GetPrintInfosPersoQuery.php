<?php

namespace App\Patient\Application\Query\GetPrintInfosPerso;

final class GetPrintInfosPersoQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

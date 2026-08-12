<?php

namespace App\Patient\Application\Query\GetPrintFicheData;

final class GetPrintFicheDataQuery
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $ficheId,
    ) {
    }
}

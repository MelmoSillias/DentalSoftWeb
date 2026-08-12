<?php

namespace App\Patient\Application\Query\GetPrintFiche;

final class GetPrintFicheQuery
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $ficheId,
    ) {
    }
}

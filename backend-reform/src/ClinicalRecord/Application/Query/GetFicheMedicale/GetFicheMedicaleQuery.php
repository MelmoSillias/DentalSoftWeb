<?php

namespace App\ClinicalRecord\Application\Query\GetFicheMedicale;

final class GetFicheMedicaleQuery
{
    public function __construct(public readonly int $ficheId)
    {
    }
}

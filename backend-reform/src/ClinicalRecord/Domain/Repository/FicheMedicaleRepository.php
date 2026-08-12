<?php

namespace App\ClinicalRecord\Domain\Repository;

use App\ClinicalRecord\Domain\Model\FicheMedicale;
use App\ClinicalRecord\Domain\ValueObject\FicheMedicaleId;

interface FicheMedicaleRepository
{
    public function save(FicheMedicale $fiche): void;

    public function findById(FicheMedicaleId $id): ?FicheMedicale;
}

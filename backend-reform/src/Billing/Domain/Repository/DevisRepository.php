<?php

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\Devis;
use App\Billing\Domain\ValueObject\DevisId;

interface DevisRepository
{
    public function save(Devis $devis): void;

    public function findById(DevisId $id): ?Devis;
}

<?php

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\Consommable;
use App\Inventory\Domain\ValueObject\ConsommableId;

interface ConsommableRepository
{
    public function save(Consommable $consommable): void;

    public function findById(ConsommableId $id): ?Consommable;
}

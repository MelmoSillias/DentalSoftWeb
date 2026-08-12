<?php

namespace App\Scheduling\Domain\Repository;

use App\Scheduling\Domain\Model\Rdv;
use App\Scheduling\Domain\ValueObject\RdvId;

interface RdvRepository
{
    public function save(Rdv $rdv): void;

    public function findById(RdvId $id): ?Rdv;
}

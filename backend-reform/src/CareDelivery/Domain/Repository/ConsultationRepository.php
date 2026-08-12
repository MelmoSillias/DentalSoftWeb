<?php

namespace App\CareDelivery\Domain\Repository;

use App\CareDelivery\Domain\Model\Consultation;
use App\CareDelivery\Domain\ValueObject\ConsultationId;

interface ConsultationRepository
{
    public function save(Consultation $consultation): void;

    public function findById(ConsultationId $id): ?Consultation;
}

<?php

namespace App\Communication\Domain\Repository;

use App\Communication\Domain\Model\SmsQueueItem;
use App\Communication\Domain\ValueObject\SmsQueueId;

interface SmsQueueRepository
{
    public function save(SmsQueueItem $item): void;

    public function findById(SmsQueueId $id): ?SmsQueueItem;
}

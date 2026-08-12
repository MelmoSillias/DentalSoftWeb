<?php

namespace App\Communication\Application\Command\UpdateSmsQueueItem;

use DateTimeImmutable;

final class UpdateSmsQueueItemCommand
{
    public function __construct(
        public readonly int $queueId,
        public readonly string $action,
        public readonly ?DateTimeImmutable $sendAt = null,
    ) {
    }
}

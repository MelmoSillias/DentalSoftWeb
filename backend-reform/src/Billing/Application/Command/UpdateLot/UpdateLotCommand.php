<?php

namespace App\Billing\Application\Command\UpdateLot;

use DateTimeInterface;

final class UpdateLotCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly ?string $description = null,
        public readonly ?DateTimeInterface $dateDebut = null,
        public readonly ?DateTimeInterface $dateFin = null,
    ) {
    }
}

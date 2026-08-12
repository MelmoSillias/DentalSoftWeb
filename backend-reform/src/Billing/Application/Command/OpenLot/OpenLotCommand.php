<?php

namespace App\Billing\Application\Command\OpenLot;

use DateTimeInterface;

final class OpenLotCommand
{
    public function __construct(
        public readonly string $assuranceCode,
        public readonly ?string $description = null,
        public readonly ?DateTimeInterface $dateDebut = null,
        public readonly ?DateTimeInterface $dateFin = null,
    ) {
    }
}

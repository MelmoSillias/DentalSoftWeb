<?php

namespace App\Billing\Application\Command\RefundLot;

use DateTimeInterface;

final class RefundLotCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly int $modeId,
        public readonly ?float $amount = null,
        public readonly ?DateTimeInterface $date = null,
    ) {
    }
}

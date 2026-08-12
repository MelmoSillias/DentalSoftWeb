<?php

namespace App\Billing\Application\Command\PayPatientShare;

use DateTimeInterface;

final class PayPatientShareCommand
{
    public function __construct(
        public readonly int $factureId,
        public readonly int $modeId,
        public readonly ?float $amount = null,
        public readonly ?DateTimeInterface $date = null,
    ) {
    }
}

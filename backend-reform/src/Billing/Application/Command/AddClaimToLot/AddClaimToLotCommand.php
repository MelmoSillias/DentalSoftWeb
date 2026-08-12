<?php

namespace App\Billing\Application\Command\AddClaimToLot;

final class AddClaimToLotCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly int $factureId,
    ) {
    }
}

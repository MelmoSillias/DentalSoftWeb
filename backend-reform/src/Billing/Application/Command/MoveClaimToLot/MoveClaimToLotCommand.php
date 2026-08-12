<?php

namespace App\Billing\Application\Command\MoveClaimToLot;

final class MoveClaimToLotCommand
{
    public function __construct(
        public readonly int $factureId,
        public readonly int $lotId,
    ) {
    }
}

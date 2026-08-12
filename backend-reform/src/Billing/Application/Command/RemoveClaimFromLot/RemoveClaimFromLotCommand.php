<?php

namespace App\Billing\Application\Command\RemoveClaimFromLot;

final class RemoveClaimFromLotCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly int $factureId,
    ) {
    }
}

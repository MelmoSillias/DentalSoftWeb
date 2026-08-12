<?php

namespace App\Billing\Application\Command\CancelLotRecovery;

final class CancelLotRecoveryCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly ?string $comment = null,
    ) {
    }
}

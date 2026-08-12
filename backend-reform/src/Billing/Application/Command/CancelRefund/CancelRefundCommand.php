<?php

namespace App\Billing\Application\Command\CancelRefund;

final class CancelRefundCommand
{
    public function __construct(
        public readonly int $lotId,
        public readonly int $transactionId,
        public readonly ?string $comment = null,
    ) {
    }
}

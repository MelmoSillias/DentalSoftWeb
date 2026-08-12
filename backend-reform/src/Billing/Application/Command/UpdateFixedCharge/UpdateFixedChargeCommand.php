<?php

namespace App\Billing\Application\Command\UpdateFixedCharge;

final class UpdateFixedChargeCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $id,
        public readonly array $data,
    ) {
    }
}

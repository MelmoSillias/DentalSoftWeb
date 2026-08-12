<?php

namespace App\Billing\Application\Command\CreateFixedCharge;

final class CreateFixedChargeCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }
}

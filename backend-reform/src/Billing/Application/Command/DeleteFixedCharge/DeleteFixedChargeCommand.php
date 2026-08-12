<?php

namespace App\Billing\Application\Command\DeleteFixedCharge;

final class DeleteFixedChargeCommand
{
    public function __construct(public readonly int $id)
    {
    }
}

<?php

namespace App\Billing\Application\Command\ConfirmLot;

final class ConfirmLotCommand
{
    public function __construct(public readonly int $lotId)
    {
    }
}

<?php

namespace App\Billing\Application\Command\UnconfirmLot;

final class UnconfirmLotCommand
{
    public function __construct(public readonly int $lotId)
    {
    }
}

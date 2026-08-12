<?php

namespace App\Billing\Application\Command\SendLot;

final class SendLotCommand
{
    public function __construct(public readonly int $lotId)
    {
    }
}

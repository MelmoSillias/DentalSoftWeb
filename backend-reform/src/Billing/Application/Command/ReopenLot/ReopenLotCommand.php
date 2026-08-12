<?php

namespace App\Billing\Application\Command\ReopenLot;

final class ReopenLotCommand
{
    public function __construct(public readonly int $lotId)
    {
    }
}

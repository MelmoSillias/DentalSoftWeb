<?php

namespace App\Billing\Application\Query\GetLot;

final class GetLotQuery
{
    public function __construct(public readonly int $lotId)
    {
    }
}

<?php

namespace App\CareDelivery\Application\Query\GetOrdonnance;

final class GetOrdonnanceQuery
{
    public function __construct(public readonly int $ordonnanceId)
    {
    }
}

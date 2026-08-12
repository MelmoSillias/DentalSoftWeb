<?php

namespace App\CareDelivery\Application\Query\ListOrdonnances;

final class ListOrdonnancesQuery
{
    public function __construct(public readonly int $consultationId)
    {
    }
}

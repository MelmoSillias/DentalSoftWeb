<?php

namespace App\CareDelivery\Application\Query;

final readonly class GetOrdonnance
{
    public function __construct(public int $id)
    {
    }
}

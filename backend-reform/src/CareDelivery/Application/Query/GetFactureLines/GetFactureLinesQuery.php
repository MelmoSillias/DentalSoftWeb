<?php

namespace App\CareDelivery\Application\Query\GetFactureLines;

final class GetFactureLinesQuery
{
    public function __construct(public readonly int $consultationId)
    {
    }
}

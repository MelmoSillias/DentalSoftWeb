<?php

namespace App\Billing\Application\Query\GetDevis;

final class GetDevisQuery
{
    public function __construct(public readonly int $devisId)
    {
    }
}

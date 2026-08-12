<?php

namespace App\Billing\Application\Query\GetClaimDetail;

final class GetClaimDetailQuery
{
    public function __construct(public readonly int $factureId)
    {
    }
}

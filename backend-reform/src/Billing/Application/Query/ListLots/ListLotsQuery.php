<?php

namespace App\Billing\Application\Query\ListLots;

final class ListLotsQuery
{
    public function __construct(
        public readonly string $assuranceCode,
        public readonly ?string $statut = null,
    ) {
    }
}

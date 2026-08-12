<?php

namespace App\Billing\Application\Query\GetFinanceCrossTable;

final class GetFinanceCrossTableQuery
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly string $type = 'revenue',
    ) {
    }
}

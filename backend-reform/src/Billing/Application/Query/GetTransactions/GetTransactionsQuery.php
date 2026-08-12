<?php

namespace App\Billing\Application\Query\GetTransactions;

use DateTimeInterface;

final class GetTransactionsQuery
{
    public function __construct(
        public readonly DateTimeInterface $startDate,
        public readonly DateTimeInterface $endDate,
    ) {
    }
}

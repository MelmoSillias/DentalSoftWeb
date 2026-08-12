<?php

namespace App\Billing\Application\Query\ListPaiementsFactures;

use DateTimeInterface;

final class ListPaiementsFacturesQuery
{
    public function __construct(
        public readonly DateTimeInterface $start,
        public readonly DateTimeInterface $end,
    ) {
    }
}

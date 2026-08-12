<?php

namespace App\Reporting\Application\Query\GetReceptionStats;

use DateTimeImmutable;

final class GetReceptionStatsQuery
{
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {
    }
}

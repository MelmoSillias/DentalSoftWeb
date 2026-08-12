<?php

namespace App\Reporting\Application\Query\GetGlobalStats;

final class GetGlobalStatsQuery
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }
}

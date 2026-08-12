<?php

namespace App\Communication\Application\Query\GetSmsStats;

final class GetSmsStatsQuery
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }
}

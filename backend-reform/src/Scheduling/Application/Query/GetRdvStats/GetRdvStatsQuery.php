<?php

namespace App\Scheduling\Application\Query\GetRdvStats;

final class GetRdvStatsQuery
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $start = null,
        public readonly ?string $end = null,
        public readonly ?int $medecinId = null,
    ) {
    }
}

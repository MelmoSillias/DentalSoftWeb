<?php

namespace App\Patient\Application\Query\GetPatientOverviewStats;

final class GetPatientOverviewStatsQuery
{
    public function __construct(
        public readonly ?object $user = null,
        public readonly bool $medecinOnly = false,
    ) {
    }
}

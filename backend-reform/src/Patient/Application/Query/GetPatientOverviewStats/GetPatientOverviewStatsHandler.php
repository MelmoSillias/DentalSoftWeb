<?php

namespace App\Patient\Application\Query\GetPatientOverviewStats;

use App\Patient\Application\Port\PatientReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetPatientOverviewStatsHandler implements QueryHandler
{
    public function __construct(private readonly PatientReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetPatientOverviewStatsQuery $query): array
    {
        return $this->readPort->getOverviewStats($query->user, $query->medecinOnly);
    }
}

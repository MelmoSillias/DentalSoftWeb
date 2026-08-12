<?php

namespace App\Reporting\Application\Query\GetGlobalStats;

use App\Reporting\Application\Port\ReportReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetGlobalStatsHandler implements QueryHandler
{
    public function __construct(private readonly ReportReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetGlobalStatsQuery $query): array
    {
        return $this->readPort->globalStats($query->from, $query->to);
    }
}

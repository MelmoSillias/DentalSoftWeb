<?php

namespace App\Reporting\Application\Query\GetReceptionStats;

use App\Reporting\Application\Port\ReportReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetReceptionStatsHandler implements QueryHandler
{
    public function __construct(private readonly ReportReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetReceptionStatsQuery $query): array
    {
        return $this->readPort->getReceptionStats($query->from, $query->to);
    }
}

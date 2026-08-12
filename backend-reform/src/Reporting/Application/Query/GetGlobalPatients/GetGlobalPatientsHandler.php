<?php

namespace App\Reporting\Application\Query\GetGlobalPatients;

use App\Reporting\Application\Port\ReportReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetGlobalPatientsHandler implements QueryHandler
{
    public function __construct(private readonly ReportReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetGlobalPatientsQuery $query): array
    {
        return $this->readPort->globalPatients();
    }
}

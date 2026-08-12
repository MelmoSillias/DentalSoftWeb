<?php

namespace App\Reporting\Application\Query\GetReport;

use App\Reporting\Application\Port\ReportReadPort;
use App\Reporting\Domain\Model\ReportRequest;
use App\Shared\Application\Bus\QueryHandler;

final class GetReportHandler implements QueryHandler
{
    public function __construct(private readonly ReportReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetReportQuery $query): array
    {
        $period = $query->period !== '' ? $query->period : 'month';
        // Legacy resolveReportRange defaults unknown periods to month; keep same contract.
        $normalizedPeriod = in_array($period, ['today', 'week', 'month', 'year', 'custom'], true)
            ? $period
            : 'month';

        $employeeId = null;
        if ($query->employeeId !== null && $query->employeeId !== '') {
            $employeeId = (int) $query->employeeId;
        }

        ReportRequest::create($normalizedPeriod, $query->start, $query->end, $employeeId);

        return $this->readPort->getReportsData(
            $normalizedPeriod,
            $query->start,
            $query->end,
            $query->employeeId !== null ? (string) $query->employeeId : null,
        );
    }
}

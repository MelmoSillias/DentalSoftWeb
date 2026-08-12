<?php

namespace App\Reporting\Infrastructure\Adapter;

use App\Reporting\Application\Port\ReportReadPort;
use App\Reporting\Service\ReportService;

final class LegacyReportReadAdapter implements ReportReadPort
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function getReportsData(
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null,
        ?string $employeeId = null,
    ): array {
        return $this->reportService->getReportsData($period, $customStart, $customEnd, $employeeId);
    }

    public function globalStats(?string $from, ?string $to): array
    {
        return $this->reportService->globalStats($from, $to);
    }

    public function globalPatients(): array
    {
        return $this->reportService->globalPatients();
    }

    public function getReceptionStats(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->reportService->getReceptionStats($from, $to);
    }
}

<?php

declare(strict_types=1);

namespace App\Focus\Infrastructure\Reporting;

use App\Focus\Application\Port\DashboardReportingInterface;
use App\Reporting\Application\Service\ReportService;

final class ReportingDashboardProvider implements DashboardReportingInterface
{
    public function __construct(private ReportService $reports) {}
    public function cashRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return $this->reports->computeCashRevenueForPeriod($from,$to);
    }
    public function doctorReports(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->reports->periodicDoctorReports($from,$to);
    }
}

<?php

declare(strict_types=1);

namespace App\Focus\Application\Port;

interface DashboardReportingInterface
{
    public function cashRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): float;
    /** @return array{kpi:array,doctors:array} */
    public function doctorReports(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}

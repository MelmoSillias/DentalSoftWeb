<?php

namespace App\Reporting\Application\Query\GetReport;

final class GetReportQuery
{
    public function __construct(
        public readonly string $period = 'month',
        public readonly ?string $start = null,
        public readonly ?string $end = null,
        public readonly mixed $employeeId = null,
    ) {
    }
}

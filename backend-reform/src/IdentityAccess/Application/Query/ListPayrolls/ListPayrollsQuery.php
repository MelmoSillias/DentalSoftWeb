<?php

namespace App\IdentityAccess\Application\Query\ListPayrolls;

final class ListPayrollsQuery
{
    public function __construct(
        public readonly int $start = 0,
        public readonly int $length = 10,
        public readonly ?int $employeeId = null,
        public readonly ?int $month = null,
        public readonly ?int $year = null,
    ) {
    }
}

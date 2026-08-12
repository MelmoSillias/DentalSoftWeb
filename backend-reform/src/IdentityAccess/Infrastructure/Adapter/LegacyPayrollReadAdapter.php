<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\PayrollReadPort;
use App\IdentityAccess\Service\PayrollService;

final class LegacyPayrollReadAdapter implements PayrollReadPort
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function listPayrolls(int $start, int $length, ?int $employeeId, ?int $month, ?int $year): array
    {
        return $this->payrollService->listPayrolls($start, $length, $employeeId, $month, $year);
    }
}

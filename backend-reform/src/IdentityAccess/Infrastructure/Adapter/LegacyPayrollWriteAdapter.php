<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\PayrollWritePort;
use App\IdentityAccess\Service\PayrollService;

final class LegacyPayrollWriteAdapter implements PayrollWritePort
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function createSalaryPayment(array $payload): array
    {
        return $this->payrollService->createSalaryPayment($payload);
    }
}

<?php

namespace App\IdentityAccess\Application\Port;

interface PayrollReadPort
{
    /**
     * @return array{data: list<array<string, mixed>>, total: int, filtered: int}
     */
    public function listPayrolls(int $start, int $length, ?int $employeeId, ?int $month, ?int $year): array;
}

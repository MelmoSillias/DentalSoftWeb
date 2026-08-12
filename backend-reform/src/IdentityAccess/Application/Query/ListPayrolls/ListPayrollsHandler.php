<?php

namespace App\IdentityAccess\Application\Query\ListPayrolls;

use App\IdentityAccess\Application\Port\PayrollReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListPayrollsHandler implements QueryHandler
{
    public function __construct(private readonly PayrollReadPort $readPort)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, filtered: int}
     */
    public function __invoke(ListPayrollsQuery $query): array
    {
        return $this->readPort->listPayrolls(
            $query->start,
            $query->length,
            $query->employeeId,
            $query->month,
            $query->year,
        );
    }
}

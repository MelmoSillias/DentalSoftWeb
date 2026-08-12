<?php

namespace App\IdentityAccess\Application\Query\GetEmployee;

use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetEmployeeHandler implements QueryHandler
{
    public function __construct(private readonly EmployeeReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetEmployeeQuery $query): ?array
    {
        return $this->readPort->getEmployeeDetails($query->employeeId);
    }
}

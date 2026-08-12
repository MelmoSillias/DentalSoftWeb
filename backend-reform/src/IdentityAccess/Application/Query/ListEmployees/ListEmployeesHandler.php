<?php

namespace App\IdentityAccess\Application\Query\ListEmployees;

use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListEmployeesHandler implements QueryHandler
{
    public function __construct(private readonly EmployeeReadPort $readPort)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, filtered: int}
     */
    public function __invoke(ListEmployeesQuery $query): array
    {
        return $this->readPort->listEmployeesPaginated($query->start, $query->length, $query->searchValue);
    }
}

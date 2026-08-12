<?php

namespace App\IdentityAccess\Application\Query\ListInfirmiers;

use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListInfirmiersHandler implements QueryHandler
{
    public function __construct(private readonly EmployeeReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListInfirmiersQuery $query): array
    {
        return $this->readPort->listInfirmiers();
    }
}

<?php

namespace App\IdentityAccess\Application\Query\ListMedecins;

use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListMedecinsHandler implements QueryHandler
{
    public function __construct(private readonly EmployeeReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListMedecinsQuery $query): array
    {
        return $this->readPort->listMedecins();
    }
}

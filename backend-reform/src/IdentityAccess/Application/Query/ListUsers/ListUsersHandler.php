<?php

namespace App\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Port\UserAdminReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListUsersHandler implements QueryHandler
{
    public function __construct(private readonly UserAdminReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListUsersQuery $query): array
    {
        return $this->readPort->getUserList();
    }
}

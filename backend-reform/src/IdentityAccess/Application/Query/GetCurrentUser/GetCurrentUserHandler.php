<?php

namespace App\IdentityAccess\Application\Query\GetCurrentUser;

use App\IdentityAccess\Application\Port\CurrentUserReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetCurrentUserHandler implements QueryHandler
{
    public function __construct(private readonly CurrentUserReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetCurrentUserQuery $query): array
    {
        return $this->readPort->getCurrentUserData();
    }
}

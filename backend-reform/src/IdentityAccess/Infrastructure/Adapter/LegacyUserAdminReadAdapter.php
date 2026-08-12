<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\UserAdminReadPort;
use App\IdentityAccess\Service\UserManagementService;

final class LegacyUserAdminReadAdapter implements UserAdminReadPort
{
    public function __construct(private readonly UserManagementService $userManagementService)
    {
    }

    public function getUserList(): array
    {
        return $this->userManagementService->getUserList();
    }
}

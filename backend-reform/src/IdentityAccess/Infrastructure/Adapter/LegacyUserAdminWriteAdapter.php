<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\UserAdminWritePort;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Service\UserManagementService;

final class LegacyUserAdminWriteAdapter implements UserAdminWritePort
{
    public function __construct(private readonly UserManagementService $userManagementService)
    {
    }

    public function createUser(array $data, ?object $actor = null): array
    {
        return $this->userManagementService->createUser(
            $data,
            $actor instanceof User ? $actor : null,
        );
    }

    public function updateUser(array $data, ?object $actor = null): array
    {
        return $this->userManagementService->updateUser(
            $data,
            $actor instanceof User ? $actor : null,
        );
    }

    public function deleteUser(array $data, ?object $actor = null): array
    {
        return $this->userManagementService->deleteUser(
            $data,
            $actor instanceof User ? $actor : null,
        );
    }

    public function resetPassword(array $data, ?object $actor = null): array
    {
        return $this->userManagementService->resetPassword(
            $data,
            $actor instanceof User ? $actor : null,
        );
    }
}

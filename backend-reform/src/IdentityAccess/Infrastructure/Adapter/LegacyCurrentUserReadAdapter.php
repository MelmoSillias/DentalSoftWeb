<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\CurrentUserReadPort;
use App\IdentityAccess\Service\AuthService;

final class LegacyCurrentUserReadAdapter implements CurrentUserReadPort
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function getCurrentUserData(): array
    {
        return $this->authService->me();
    }
}

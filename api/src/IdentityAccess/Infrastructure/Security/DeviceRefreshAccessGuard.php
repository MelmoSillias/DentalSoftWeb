<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\RefreshAccessGuardInterface;
use App\IdentityAccess\Application\Service\UserDeviceService;
use App\IdentityAccess\Domain\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DeviceRefreshAccessGuard implements RefreshAccessGuardInterface
{
    public function __construct(private RequestStack $requests, private UserDeviceService $devices) {}
    public function check(User $user): void
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null || !$this->devices->enforceDeviceForRequest($user, $request)['allowed']) {
            throw new AccessDeniedHttpException('device_not_allowed');
        }
    }
}

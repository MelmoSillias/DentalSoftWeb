<?php

namespace App\Communication\Infrastructure\Adapter;

use App\Communication\Application\Port\NotificationWritePort;
use App\Communication\Service\NotificationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class LegacyNotificationWriteAdapter implements NotificationWritePort
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function markAsRead(User $user, array $ids = []): int
    {
        return $this->notificationService->markAsRead($user, $ids);
    }
}

<?php

namespace App\Communication\Application\Port;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

interface NotificationWritePort
{
    /**
     * @param list<int|string> $ids
     */
    public function markAsRead(User $user, array $ids = []): int;
}

<?php

namespace App\Communication\Application\Command\MarkNotificationsRead;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class MarkNotificationsReadCommand
{
    /**
     * @param list<int|string> $ids
     */
    public function __construct(
        public readonly User $user,
        public readonly array $ids = [],
    ) {
    }
}

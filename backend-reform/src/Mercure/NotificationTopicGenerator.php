<?php

namespace App\Mercure;

use App\Entity\User;

final class NotificationTopicGenerator
{
    public function __construct(
        private readonly string $publicHubUrl,
    ) {
    }

    public function forUser(User $user): ?string
    {
        $id = $user->getId();
        if ($id === null) {
            return null;
        }

        return sprintf('%s/users/%d', rtrim($this->publicHubUrl, '/'), $id);
    }
}

<?php

namespace App\Mercure;

use App\Entity\User;

final class NotificationTopicGenerator
{
    public function __construct(
        private readonly string $secret = "",
    ) {
    }

    public function forUser(User $user): ?string
    {
        $id = $user->getId();
        if ($id === null) {
            return null;
        }

        $signature = substr(hash_hmac('sha256', (string) $id, $this->secret), 0, 16);

        return sprintf('urn:dentalsoft:notifications:%d:%s', $id, $signature);
    }
}

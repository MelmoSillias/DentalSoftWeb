<?php

namespace App\Communication\Mercure;

use App\IdentityAccess\Entity\User;

final class NotificationTopicGenerator
{
    public function __construct(
        private readonly string $publicHubUrl,
        private readonly string $topicNamespace,
    ) {
    }

    public function forUser(User $user): ?string
    {
        $id = $user->getId();
        if ($id === null) {
            return null;
        }

        $namespace = trim($this->topicNamespace, "/ \t\n\r\0\x0B");
        if ($namespace === '') {
            $namespace = 'default';
        }

        return sprintf(
            '%s/instances/%s/users/%d',
            rtrim($this->publicHubUrl, '/'),
            rawurlencode($namespace),
            $id
        );
    }
}
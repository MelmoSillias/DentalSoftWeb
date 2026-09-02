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

    /**
     * @deprecated Use forUserNotifications() instead.
     */
    public function forUser(User $user): ?string
    {
        return $this->forUserNotifications($user);
    }

    public function forUserNotifications(User $user): ?string
    {
        $id = $user->getId();
        if ($id === null) {
            return null;
        }

        return sprintf(
            '%s/users/%d/notifications',
            $this->basePath(),
            $id
        );
    }

    public function forFocusChannel(): string
    {
        return sprintf('%s/focus', $this->basePath());
    }

    private function basePath(): string
    {
        $namespace = trim($this->topicNamespace, "/ \t\n\r\0\x0B");
        if ($namespace === '') {
            $namespace = 'default';
        }

        return sprintf(
            '%s/instances/%s',
            rtrim($this->publicHubUrl, '/'),
            rawurlencode($namespace)
        );
    }
}

<?php

namespace App\Communication\Service;

use App\Communication\Mercure\NotificationTopicGenerator;
use App\IdentityAccess\Entity\User;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class MercureAuthorizationService
{
    private const STAFF_ROLES = [
        'ROLE_ADMIN',
        'ROLE_RECEPTION',
        'ROLE_RECEPTIONNISTE',
        'ROLE_SECRETAIRE',
        'ROLE_MEDECIN',
    ];

    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly string $mercurePublicUrl,
        private readonly TokenFactoryInterface $tokenFactory,
    ) {
    }

    /**
     * @return array{publicUrl: string, topics: list<string>, topic: string|null, token: string, expiresAt: string}|null
     */
    public function buildSubscription(User $user): ?array
    {
        $topics = $this->resolveTopics($user);
        if ($topics === []) {
            return null;
        }

        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', self::TOKEN_TTL_SECONDS));
        $token = $this->tokenFactory->create(
            subscribe: $topics,
            additionalClaims: ['exp' => $expiresAt],
        );

        return [
            'publicUrl' => $this->mercurePublicUrl,
            'topics' => $topics,
            'topic' => $topics[0] ?? null,
            'token' => $token,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveTopics(User $user): array
    {
        $topics = [];

        if ($user->isNotificationsEnabled()) {
            $notificationsTopic = $this->topicGenerator->forUserNotifications($user);
            if ($notificationsTopic !== null) {
                $topics[] = $notificationsTopic;
            }
        }

        if ($this->canAccessFocus($user)) {
            $topics[] = $this->topicGenerator->forFocusChannel();
        }

        return $topics;
    }

    private function canAccessFocus(User $user): bool
    {
        return array_intersect(self::STAFF_ROLES, $user->getRoles()) !== [];
    }
}

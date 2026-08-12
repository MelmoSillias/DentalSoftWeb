<?php

namespace App\Communication\Service;

use App\Communication\Mercure\NotificationTopicGenerator;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class MercureAuthorizationService
{
    public function __construct(
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly string $mercureJwtSecret,
        private readonly string $mercurePublicUrl,
    ) {
    }

    /**
     * @return array{publicUrl: string, topic: string, token: string}|null
     */
    public function buildSubscription(User $user): ?array
    {
        $topic = $this->topicGenerator->forUser($user);
        if ($topic === null) {
            return null;
        }

        $now = time();
        $payload = [
            'mercure' => [
                'subscribe' => [$topic],
            ],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        return [
            'publicUrl' => $this->mercurePublicUrl,
            'topic' => $topic,
            'token' => $this->buildJwt($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $encodedHeader = $this->base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->mercureJwtSecret, true);

        return sprintf('%s.%s.%s', $encodedHeader, $encodedPayload, $this->base64UrlEncode($signature));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

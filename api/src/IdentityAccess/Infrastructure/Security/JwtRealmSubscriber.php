<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class JwtRealmSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requests) {}
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created' => 'onCreated',
            'lexik_jwt_authentication.on_jwt_decoded' => 'onDecoded',
            'lexik_jwt_authentication.on_jwt_authenticated' => 'onAuthenticated',
        ];
    }

    public function onCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) return;
        $payload = $event->getData();
        $payload['realm'] = AuthRealm::forUser($user)?->value;
        $payload['credential_version'] = $user->getAuthenticationVersion();
        $event->setData($payload);
    }

    public function onDecoded(JWTDecodedEvent $event): void
    {
        $realm = AuthRealm::tryFrom((string) ($event->getPayload()['realm'] ?? ''));
        $path = $this->requests->getCurrentRequest()?->getPathInfo() ?? '';
        $common = in_array($path, [
            '/api/me', '/api/token/validate', '/api/auth/session',
            '/api/me/password', '/api/me/change-password', '/api/me/realtime',
            '/api/me/push/config', '/api/me/push/subscription',
        ], true) || preg_match('#^/api/me/(notifications|notification-read-receipts)(?:/|$)#', $path) === 1;
        $patient = preg_match('#^/api/(patient-portal|portal-patient)(?:/|$)#', $path) === 1;
        if ($realm === null || (!$common && $realm !== ($patient ? AuthRealm::Patient : AuthRealm::App))) $event->markAsInvalid();
    }

    public function onAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $user = $event->getToken()->getUser();
        $payload = $event->getPayload();
        if (!$user instanceof User || !$user->isActive()
            || AuthRealm::forUser($user)?->value !== ($payload['realm'] ?? null)
            || !hash_equals($user->getAuthenticationVersion(), (string) ($payload['credential_version'] ?? ''))) {
            throw new BadCredentialsException();
        }
    }
}

<?php

namespace App\IdentityAccess\EventSubscriber;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class UserSessionActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->storeEvent(
            $user,
            'Connexion réussie',
            'Connexion depuis ' . ($event->getRequest()?->getClientIp() ?? 'IP inconnue'),
            'success',
            'fas fa-sign-in-alt',
            $event->getRequest()
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $tokenUser = $event->getToken()?->getUser();
        if (!$tokenUser instanceof User) {
            return;
        }

        $this->storeEvent(
            $tokenUser,
            'Déconnexion',
            'Vous vous êtes déconnecté',
            'warning',
            'fas fa-sign-out-alt',
            $event->getRequest()
        );
    }

    private function storeEvent(User $user, string $title, string $subtitle, string $badge, string $icon, ?Request $request = null): void
    {
        $session = $this->requestStack->getSession();
        if (!$session || null === $user->getId()) {
            return;
        }

        $key = sprintf('user_activity_feed_%d', $user->getId());
        $events = $session->get($key, []);

        array_unshift($events, [
            'title' => $title,
            'subtitle' => $subtitle,
            'badge' => $badge,
            'icon' => $icon,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'ip' => $request?->getClientIp(),
            'userAgent' => $request?->headers->get('User-Agent'),
        ]);

        $session->set($key, array_slice($events, 0, 10));
    }
}

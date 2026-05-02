<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Exception\RetryableException;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class NotificationAutoReadSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SecurityBundleSecurity $security,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /** @return array<string, array{0: string, 1?: int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $path = $event->getRequest()->getPathInfo() ?? '';
        if ($path === '') {
            return;
        }

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $this->notificationRepository->markUnreadMatchingPathAsRead($user, $path, 50);
                break;
            } catch (RetryableException) {
                if ($attempt === 2) {
                    break;
                }
            } catch (\Throwable) {
                break;
            }
        }
    }
}

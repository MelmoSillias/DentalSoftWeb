<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

final class NotificationAutoReadSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SecurityBundleSecurity $security,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
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

        $notifications = $this->notificationRepository->findUnreadWithLink($user, 50);
        $updated = 0;

        foreach ($notifications as $notification) {
            if (!$notification instanceof Notification) {
                continue;
            }

            $link = $notification->getLink();
            if (!$link) {
                continue;
            }

            $linkPath = parse_url($link, PHP_URL_PATH) ?: $link;
            if ($linkPath && str_starts_with($path, $linkPath)) {
                $notification->setEtatVu('vu');
                ++$updated;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }
    }
}

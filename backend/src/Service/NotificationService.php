<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly NotificationRealtimePublisher $realtimePublisher,
    ) {
    }

    public function notify(
        User $recipient,
        string $message,
        NotificationPriority|string $priority = Notification::PRIORITY_INFO,
        ?string $link = null,
        ?string $type = null,
        ?User $emitter = null,
    ): Notification {
        if (!$recipient->isNotificationsEnabled()) {
            return $this->buildNotification($recipient, $message, $priority, $link, $type, $emitter);
        }

        $notification = $this->buildNotification($recipient, $message, $priority, $link, $type, $emitter);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        $this->realtimePublisher->publish($notification);

        return $notification;
    }

    /**
     * @param iterable<User> $recipients
     */
    public function notifyMany(
        iterable $recipients,
        string $message,
        NotificationPriority|string $priority = Notification::PRIORITY_INFO,
        ?string $link = null,
        ?string $type = null,
        ?User $emitter = null,
    ): int {
        $sent = 0;
        $notifications = [];

        foreach ($recipients as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }

            if (!$recipient->isNotificationsEnabled()) {
                continue;
            }

            $notification = $this->buildNotification($recipient, $message, $priority, $link, $type, $emitter);
            $this->entityManager->persist($notification);
            $notifications[] = $notification;
            ++$sent;
        }

        if ($sent > 0) {
            $this->entityManager->flush();
            foreach ($notifications as $notification) {
                $this->realtimePublisher->publish($notification);
            }
        }

        return $sent;
    }

    /**
     * @param iterable<User> $recipients
     */
    public function notifyUsers(
        iterable $recipients,
        string $message,
        ?string $link = null,
        NotificationPriority|string $priority = Notification::PRIORITY_INFO,
        ?User $emitter = null,
        mixed $subject = null,
        mixed $context = null,
        ?string $type = null,
    ): int {
        return $this->notifyMany($recipients, $message, $priority, $link, $type, $emitter);
    }

    /**
     * @param list<string> $roles
     */
    public function notifyRoles(
        array $roles,
        string $message,
        ?string $link = null,
        NotificationPriority|string $priority = Notification::PRIORITY_INFO,
        ?User $emitter = null,
        mixed $subject = null,
        mixed $context = null,
        ?string $type = null,
    ): int {
        $recipients = $this->recipientResolver->forRoles($roles, $emitter);

        return $this->notifyUsers($recipients, $message, $link, $priority, $emitter, $subject, $context, $type);
    }

    private function buildNotification(
        User $recipient,
        string $message,
        NotificationPriority|string $priority,
        ?string $link,
        ?string $type,
        ?User $emitter,
    ): Notification {
        $normalizedPriority = $this->normalizePriority($priority);
        $finalType = $type ?? $this->mapPriorityToType($normalizedPriority);

        return (new Notification())
            ->setUser($recipient)
            ->setMessage($message)
            ->setPriority($normalizedPriority)
            ->setType($finalType)
            ->setLink($link)
            ->setEmitter($emitter)
            ->setEtatVu('non_vu')
            ->setDateEnvoi(new \DateTimeImmutable());
    }

    /**
     * @param array<int> $ids
     */
    public function markAsRead(User $user, array $ids = []): int
    {
        $qb = $this->notificationRepository->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.etatVu = :unread')
            ->setParameter('unread', 'non_vu')
            ->setParameter('user', $user);

        if ($ids !== []) {
            $qb->andWhere('n.id IN (:ids)')->setParameter('ids', $ids);
        }

        $notifications = $qb->getQuery()->getResult();

        foreach ($notifications as $notification) {
            if ($notification instanceof Notification) {
                $notification->setEtatVu('vu');
            }
        }

        if ($notifications !== []) {
            $this->entityManager->flush();
        }

        return count($notifications);
    }

    public function purgeOlderThan(\DateTimeInterface $threshold): int
    {
        return $this->notificationRepository->purgeOlderThan($threshold);
    }

    private function mapPriorityToType(string $priority): string
    {
        return match ($priority) {
            Notification::PRIORITY_CRITICAL => Notification::TYPE_DANGER,
            Notification::PRIORITY_WARNING => Notification::TYPE_WARNING,
            default => Notification::TYPE_INFO,
        };
    }

    private function normalizePriority(NotificationPriority|string $priority): string
    {
        if ($priority instanceof NotificationPriority) {
            $priority = $priority->value;
        }

        $value = strtolower((string) $priority);

        return match ($value) {
            'critical', 'critique', 'high' => Notification::PRIORITY_CRITICAL,
            'warning', 'avertissement', 'warn', 'medium' => Notification::PRIORITY_WARNING,
            'normal', 'low', 'info' => Notification::PRIORITY_INFO,
            default => Notification::PRIORITY_INFO,
        };
    }
}

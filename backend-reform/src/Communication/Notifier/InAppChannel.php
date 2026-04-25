<?php

namespace App\Communication\Notifier;

use App\Enum\NotificationPriority;
use App\Communication\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class InAppChannel implements ChannelInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $em,
    ) {
    }

    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$notification instanceof InAppNotification || !$recipient instanceof UserRecipient) {
            return;
        }

        $message = $notification->getContent() ?: $notification->getSubject();
        $priority = $notification->getAppPriority() ?? $this->mapImportance($notification->getImportance());

        $this->notificationService->notify(
            $recipient->getUser(),
            $message,
            $priority,
            $notification->getLink(),
            null,
            $notification->getEmitter(),
        );
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return $notification instanceof InAppNotification && $recipient instanceof UserRecipient;
    }

    private function mapImportance(string $importance): NotificationPriority
    {
        return match ($importance) {
            Notification::IMPORTANCE_URGENT => NotificationPriority::CRITIQUE,
            Notification::IMPORTANCE_HIGH => NotificationPriority::AVERTISSEMENT,
            default => NotificationPriority::INFO,
        };
    }
}

<?php

namespace App\Communication\Service;

use App\Communication\Entity\Notification;
use App\Communication\Mercure\NotificationTopicGenerator;
use App\Communication\Mercure\RealtimeEnvelope;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotificationRealtimePublisher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Notification $notification): void
    {
        $user = $notification->getUser();
        if ($user === null) {
            return;
        }

        $topic = $this->topicGenerator->forUserNotifications($user);
        if ($topic === null) {
            return;
        }

        $eventId = $notification->getId() ? (string) $notification->getId() : uniqid('notification-', true);
        $payload = [
            'id' => $notification->getId(),
            'title' => 'Notification',
            'message' => $notification->getMessage(),
            'type' => $notification->getType(),
            'priority' => $notification->getPriority(),
            'status' => $notification->getEtatVu(),
            'createdAt' => $notification->getDateEnvoi()?->format(DATE_ATOM),
            'link' => $notification->getLink(),
            'emitter' => $notification->getEmitter()?->getUsername(),
        ];

        try {
            $update = new Update(
                $topic,
                RealtimeEnvelope::notification($payload, $eventId),
                true,
                $eventId,
                'notification'
            );

            $this->bus->dispatch($update);
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible d\'enfiler la notification Mercure.', [
                'exception' => $exception,
                'notificationId' => $notification->getId(),
            ]);
        }
    }
}

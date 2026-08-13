<?php

namespace App\Communication\Service;

use App\Communication\Entity\Notification;
use App\Communication\Mercure\NotificationTopicGenerator;
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

        $topic = $this->topicGenerator->forUser($user);
        if ($topic === null) {
            return;
        }

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
                json_encode($payload, JSON_THROW_ON_ERROR),
                false,
                $notification->getId() ? (string) $notification->getId() : null,
                'notification'
            );

            // Async via Messenger (UpdateHandler + ResilientMercureHub).
            $this->bus->dispatch($update);
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible d\'enfiler la notification Mercure.', [
                'exception' => $exception,
                'notificationId' => $notification->getId(),
            ]);
        }
    }
}
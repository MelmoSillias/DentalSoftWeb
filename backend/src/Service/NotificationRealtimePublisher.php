<?php

namespace App\Service;

use App\Entity\Notification;
use App\Mercure\NotificationTopicGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class NotificationRealtimePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
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

            $this->hub->publish($update);
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible de publier la notification sur Mercure.', [
                'exception' => $exception,
                'notificationId' => $notification->getId(),
            ]);
        }
    }
}

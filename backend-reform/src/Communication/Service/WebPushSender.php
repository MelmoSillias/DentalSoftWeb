<?php

namespace App\Communication\Service;

use App\Communication\Entity\Notification;
use App\Communication\Entity\WebPushSubscription;
use App\Communication\Exception\WebPushDeliveryException;
use App\Communication\Repository\NotificationRepository;
use App\Communication\Repository\WebPushSubscriptionRepository;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class WebPushSender
{
    private const TTL_SECONDS = 86400;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly WebPushSubscriptionRepository $subscriptionRepository,
        private readonly WebPushSubscriptionValidator $validator,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
        private readonly string $vapidSubject,
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
    ) {
    }

    public function send(int $notificationId): void
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('Web Push ignoré : clés VAPID absentes.', [
                'notificationId' => $notificationId,
            ]);

            return;
        }

        $notification = $this->notificationRepository->find($notificationId);
        if (!$notification instanceof Notification) {
            return;
        }

        $user = $notification->getUser();
        if ($user === null || !$user->isNotificationsEnabled()) {
            return;
        }

        $subscriptions = $this->subscriptionRepository->findForUser($user);
        if ($subscriptions === []) {
            return;
        }

        try {
            $webPush = new WebPush(
                [
                    'VAPID' => [
                        'subject' => $this->vapidSubject,
                        'publicKey' => $this->vapidPublicKey,
                        'privateKey' => $this->vapidPrivateKey,
                    ],
                ],
                ['TTL' => self::TTL_SECONDS],
                $this->httpClient,
                $this->requestFactory,
                $this->streamFactory,
            );
        } catch (\Throwable) {
            $this->logger->warning('Web Push ignoré : configuration VAPID invalide.', [
                'notificationId' => $notificationId,
            ]);

            return;
        }

        $webPush->setReuseVAPIDHeaders(true);
        $payload = $this->buildPayload($notification);
        $options = [
            'TTL' => self::TTL_SECONDS,
            'urgency' => $this->urgencyFor($notification),
            'topic' => $this->topicFor($notification),
        ];

        $queued = [];
        foreach ($subscriptions as $stored) {
            if (!$this->validator->isAllowedEndpoint($stored->getEndpoint())) {
                $this->subscriptionRepository->getEntityManager()->remove($stored);
                $this->logger->warning('Abonnement Web Push retiré : endpoint refusé.', [
                    'notificationId' => $notificationId,
                    'endpointHash' => $stored->getEndpointHash(),
                ]);
                continue;
            }

            $queued[$stored->getEndpointHash()] = $stored;
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $stored->getEndpoint(),
                    'contentEncoding' => $stored->getContentEncoding(),
                    'keys' => [
                        'p256dh' => $stored->getP256dh(),
                        'auth' => $stored->getAuth(),
                    ],
                ]),
                $payload,
                $options,
            );
        }

        if ($queued === []) {
            $this->subscriptionRepository->getEntityManager()->flush();

            return;
        }

        $transientFailure = false;
        try {
            foreach ($webPush->flush() as $report) {
                $stored = $queued[hash('sha256', $report->getEndpoint())] ?? null;
                if (!$stored instanceof WebPushSubscription) {
                    if (!$report->isSuccess() && !$report->isSubscriptionExpired()) {
                        $transientFailure = true;
                    }
                    continue;
                }

                if ($report->isSuccess()) {
                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    $this->subscriptionRepository->getEntityManager()->remove($stored);
                    $this->logger->info('Abonnement Web Push expiré, suppression.', [
                        'notificationId' => $notificationId,
                        'endpointHash' => $stored->getEndpointHash(),
                        'statusCode' => $report->getResponse()?->getStatusCode(),
                    ]);
                    continue;
                }

                $transientFailure = true;
                $this->logger->warning('Echec d\'envoi Web Push.', [
                    'notificationId' => $notificationId,
                    'endpointHash' => $stored->getEndpointHash(),
                    'statusCode' => $report->getResponse()?->getStatusCode(),
                ]);
            }
        } catch (\Throwable) {
            $this->logger->error('Echec de la session Web Push.', [
                'notificationId' => $notificationId,
            ]);
            throw new WebPushDeliveryException('Echec de la session Web Push.');
        }

        $this->subscriptionRepository->getEntityManager()->flush();

        if ($transientFailure) {
            throw new WebPushDeliveryException('Au moins un envoi Web Push a échoué.');
        }
    }

    private function isConfigured(): bool
    {
        return $this->vapidSubject !== '' && $this->vapidPublicKey !== '' && $this->vapidPrivateKey !== '';
    }

    private function buildPayload(Notification $notification): string
    {
        $message = $notification->getMessage() ?? '';
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        $payload = json_encode([
            'id' => $notification->getId(),
            'title' => 'Notification',
            'message' => $message,
            'link' => $this->validator->sanitizeLink($notification->getLink()),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($payload) > 2800) {
            $payload = json_encode([
                'id' => $notification->getId(),
                'title' => 'Notification',
                'message' => mb_substr($message, 0, 180),
                'link' => null,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return $payload;
    }

    private function urgencyFor(Notification $notification): string
    {
        return match ($notification->getPriority()) {
            Notification::PRIORITY_CRITICAL => 'high',
            Notification::PRIORITY_WARNING => 'normal',
            default => 'low',
        };
    }

    private function topicFor(Notification $notification): string
    {
        return substr('n-'.($notification->getId() ?? '0'), 0, 32);
    }
}

<?php

namespace App\Communication\Controller\Api;

use App\Communication\Entity\Notification;
use App\Communication\Repository\NotificationRepository;
use App\IdentityAccess\Entity\User;
use App\Communication\Service\MercureAuthorizationService;
use App\Communication\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationController extends AbstractController
{
    #[Route('/api/me/notifications', name: 'api_me_notifications', methods: ['GET'])]
    public function list(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->isNotificationsEnabled()) {
            return $this->json([
                'items' => [],
                'filter' => 'all',
            ]);
        }

        $filter = $request->query->get('filter', 'all');
        $limit = (int) $request->query->get('limit', 50);
        if (!in_array($filter, ['all', 'read', 'unread'], true)) {
            $filter = 'all';
        }

        $list = $notificationRepository->findByFilter($user, $filter, $limit);
        $payload = array_map(fn (Notification $notification) => $this->mapNotification($notification), $list);

        return $this->json([
            'items' => $payload,
            'filter' => $filter,
        ]);
    }

    #[Route('/api/me/notifications/mark-read', name: 'api_me_notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->isNotificationsEnabled()) {
            return $this->json(['updated' => 0]);
        }

        $ids = $request->toArray()['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->json(['updated' => 0]);
        }

        $updated = $notificationService->markAsRead($user, $ids);

        return $this->json(['updated' => $updated]);
    }

    #[Route('/api/me/notifications/mark-all', name: 'api_me_notifications_mark_all', methods: ['POST'])]
    public function markAll(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->isNotificationsEnabled()) {
            return $this->json(['updated' => 0]);
        }

        $updated = $notificationService->markAsRead($user);

        return $this->json(['updated' => $updated]);
    }

    #[Route('/api/me/notifications/mercure', name: 'api_me_notifications_mercure', methods: ['GET'])]
    public function mercure(MercureAuthorizationService $mercureAuthorizationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $subscription = $mercureAuthorizationService->buildSubscription($user);
        if ($subscription === null) {
            return $this->json([]);
        }

        return $this->json($subscription);
    }

    /** @return array<string, mixed> */
    private function mapNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'title' => 'Notification',
            'message' => $notification->getMessage(),
            'status' => $notification->getEtatVu(),
            'priority' => $notification->getPriority(),
            'type' => $notification->getType(),
            'createdAt' => $notification->getDateEnvoi()?->format('Y-m-d H:i'),
            'link' => $notification->getLink(),
        ];
    }
}
<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Mercure\NotificationTopicGenerator;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
 
class NotificationController extends AbstractController
{
    #[Route('/api/me/notifications', name: 'api_me_notifications', methods: ['GET'])]
    public function list(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $filter = $request->query->get('filter', 'all');
        $limit = (int) $request->query->get('limit', 50);
        if (!in_array($filter, ['all', 'read', 'unread'], true)) {
            $filter = 'all';
        }

        $list = $notificationRepository->findByFilter($user, $filter, $limit);
        $payload = array_map(fn(Notification $n) => $this->mapNotification($n), $list);

        return $this->json([
            'items' => $payload,
            'filter' => $filter,
        ]);
    }

    #[Route('/api/me/notifications/mark-read', name: 'api_me_notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $ids = $request->toArray()['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->json(['updated' => 0]);
        }

        $repo = $em->getRepository(Notification::class);
        $list = $repo->findBy(['id' => $ids, 'user' => $user]);
        foreach ($list as $notif) {
            $notif->setEtatVu('vu');
        }
        $em->flush();

        return $this->json(['updated' => count($list)]);
    }

    #[Route('/api/me/notifications/mark-all', name: 'api_me_notifications_mark_all', methods: ['POST'])]
    public function markAll(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $repo = $em->getRepository(Notification::class);
        $list = $repo->findBy(['user' => $user, 'etatVu' => 'non_vu']);
        foreach ($list as $notif) {
            $notif->setEtatVu('vu');
        }
        $em->flush();

        return $this->json(['updated' => count($list)]);
    }

    #[Route('/api/me/notifications/mercure', name: 'api_me_notifications_mercure', methods: ['GET'])]
    public function mercureInfo(
        #[Autowire(service: 'defaultTokenFactory')] TokenFactoryInterface $tokenFactory,
        NotificationTopicGenerator $topicGenerator,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $publicUrl,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $topic = $topicGenerator->forUser($user);
        if (!$topic) {
            return $this->json(['error' => 'Invalid user topic'], 400);
        }

        $token = $tokenFactory->create([$topic]);

        return $this->json([
            'topic' => $topic,
            'publicUrl' => $publicUrl,
            'token' => $token,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'message' => $notification->getMessage(),
            'status' => $notification->getEtatVu(),
            'priority' => $notification->getPriority(),
            'type' => $notification->getType(),
            'createdAt' => $notification->getDateEnvoi()?->format('Y-m-d H:i'),
            'link' => $notification->getLink(),
        ];
    }
}

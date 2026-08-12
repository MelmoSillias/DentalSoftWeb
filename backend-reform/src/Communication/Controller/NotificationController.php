<?php

namespace App\Communication\Controller;

use App\Communication\Infrastructure\Persistence\Doctrine\Entity\Notification;
use App\Communication\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Communication\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('', name: 'app_notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $limit = (int) $request->query->get('limit', 20);
        $limit = max(1, min(50, $limit));

        $notifications = $this->notificationRepository->findLatestForUser($user, $limit);

        return $this->json(
            array_map(fn(Notification $notification) => $this->normalize($notification), $notifications)
        );
    }

    #[Route('/read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        try {
            $payload = json_decode($request->getContent() ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json([
                'error' => 'Format JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $ids = array_map('intval', $payload['ids'] ?? []);
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        $markAll = (bool) ($payload['all'] ?? false);

        if (!$markAll && $ids === []) {
            return $this->json([
                'error' => 'Aucune notification à mettre à jour.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $updated = $this->notificationService->markAsRead($user, $markAll ? [] : $ids);

        return $this->json(['updated' => $updated]);
    }

    #[Route('/stream', name: 'app_notifications_stream', methods: ['GET'])]
    public function streamEvents(Request $request): StreamedResponse
    {
        $user = $this->requireUser();
        $lastEventId = $request->headers->get('Last-Event-ID', $request->query->get('lastEventId', '0'));
        $lastEventId = (int) $lastEventId;

        if ($request->hasSession()) {
            $request->getSession()->save();
        }

        $response = new StreamedResponse(function () use ($user, $lastEventId): void {
            $this->runStream($user, $lastEventId);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    private function runStream(User $user, int $lastEventId): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $start = time();
        $timeout = 55; // seconds
        $lastId = $lastEventId;
        $lastHeartbeat = 0;

        while ((time() - $start) < $timeout && !connection_aborted()) {
            $notifications = $this->notificationRepository->findNewerThan($user, $lastId);

            foreach ($notifications as $notification) {
                if (!$notification instanceof Notification) {
                    continue;
                }

                $lastId = $notification->getId();
                $payload = $this->normalize($notification);

                echo 'id: ' . $notification->getId() . "\n";
                echo "event: notification\n";
                echo 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";

                if (ob_get_length() !== false) {
                    @ob_flush();
                }
                flush();
            }

            if ($notifications === [] && (time() - $lastHeartbeat) >= 15) {
                echo ': heartbeat ' . time() . "\n\n";
                if (ob_get_length() !== false) {
                    @ob_flush();
                }
                flush();
                $lastHeartbeat = time();
            }

            usleep(1500000);
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function normalize(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'message' => $notification->getMessage(),
            'type' => $notification->getType(),
            'priority' => $notification->getPriority(),
            'date' => $notification->getDateEnvoi()?->format(DATE_ATOM),
            'read' => $notification->getEtatVu() === 'vu',
            'link' => $notification->getLink(),
            'emitter' => $notification->getEmitter()?->getUsername(),
        ];
    }
}

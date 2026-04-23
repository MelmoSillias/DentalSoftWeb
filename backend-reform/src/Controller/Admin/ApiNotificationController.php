<?php

namespace App\Controller\Admin;

use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ApiNotificationController extends AbstractController
{
    #[Route('/api/admin/notifications/send', name: 'api_admin_notifications_send', methods: ['POST'])]
    public function send(Request $request, UserRepository $users, NotificationService $notificationService): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $recipientIds = array_values(array_unique((array) ($data['recipients'] ?? [])));
        $message = trim((string) ($data['message'] ?? ''));
        $priority = $data['priority'] ?? 'normal';
        $link = $data['link'] ?? null;

        if (empty($recipientIds) || empty($message)) {
            return new JsonResponse(['error' => 'Recipients and message are required'], Response::HTTP_BAD_REQUEST);
        }

        $recipientEntities = $users->findBy(['id' => $recipientIds]);
        $emitter = $this->getUser() instanceof User ? $this->getUser() : null;

        $sent = 0;
        foreach ($recipientEntities as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }

            $notificationService->notify(
                $recipient,
                $message,
                $priority,
                $link ?: null,
                null,
                $emitter
            );

            ++$sent;
        }

        return new JsonResponse(['sent' => $sent]);
    }
}

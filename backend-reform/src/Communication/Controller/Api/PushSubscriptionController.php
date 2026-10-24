<?php

namespace App\Communication\Controller\Api;

use App\Communication\Service\WebPushSubscriptionManager;
use App\IdentityAccess\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PushSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
        private readonly string $vapidSubject,
    ) {
    }

    #[Route('/api/me/push/config', name: 'api_me_push_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $configured = $this->isConfigured();

        return $this->json([
            'configured' => $configured,
            'publicKey' => $configured ? $this->vapidPublicKey : null,
        ]);
    }

    #[Route('/api/me/push/subscription', name: 'api_me_push_subscription_upsert', methods: ['PUT'])]
    public function upsert(Request $request, WebPushSubscriptionManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$this->isConfigured()) {
            return $this->json(['error' => 'Web Push n\'est pas configuré.'], 503);
        }

        try {
            $payload = $request->toArray();
            $manager->upsert($user, $payload, $request->headers->get('User-Agent'));
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Abonnement push invalide.'], 422);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/api/me/push/subscription', name: 'api_me_push_subscription_delete', methods: ['DELETE'])]
    public function delete(Request $request, WebPushSubscriptionManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $endpoint = $request->toArray()['endpoint'] ?? null;
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }

        if (!is_string($endpoint) || $endpoint === '') {
            return $this->json(['error' => 'Endpoint manquant.'], 400);
        }

        if (!$manager->deleteOwned($user, $endpoint)) {
            return $this->json(['error' => 'Abonnement introuvable.'], 404);
        }

        return $this->json(['ok' => true]);
    }

    private function isConfigured(): bool
    {
        return $this->vapidSubject !== '' && $this->vapidPublicKey !== '' && $this->vapidPrivateKey !== '';
    }
}

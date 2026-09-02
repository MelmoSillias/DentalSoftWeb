<?php

namespace App\Communication\Controller\Api;

use App\Communication\Service\MercureAuthorizationService;
use App\IdentityAccess\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Routing\Attribute\Route;

final class RealtimeController extends AbstractController
{
    #[Route('/api/me/realtime', name: 'api_me_realtime', methods: ['GET'])]
    public function subscription(
        Request $request,
        MercureAuthorizationService $mercureAuthorizationService,
        Discovery $discovery,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        $subscription = $mercureAuthorizationService->buildSubscription($user);
        if ($subscription === null) {
            return $this->json(['error' => 'No realtime topics available for this user'], 400);
        }

        $discovery->addLink($request);

        return $this->json($subscription);
    }
}

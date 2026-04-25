<?php

namespace App\Config\Controller\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Settings\Service\GlobalSettingsService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
 
final class HealthController extends AbstractController
{  
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok', 
        ]);
    }

    #[Route('/api/health/current-time', name: 'api_health_current_time', methods: ['GET'])]
    public function currentTime(): JsonResponse
    {
        return $this->json([
            'currentTime' => (new \DateTime())->format(\DateTime::ATOM),
        ]);
    }
}
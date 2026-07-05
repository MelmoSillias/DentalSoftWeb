<?php

namespace App\Config\Controller\Api;

use App\Communication\Service\MercureHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly MercureHealthService $mercureHealthService,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
        ]);
    }

    #[Route('/api/health/mercure', name: 'api_health_mercure', methods: ['GET'])]
    public function mercure(): JsonResponse
    {
        $report = $this->mercureHealthService->diagnose();

        return $this->json($report, $report['status'] === 'ok' ? 200 : 503);
    }

    #[Route('/api/health/current-time', name: 'api_health_current_time', methods: ['GET'])]
    public function currentTime(): JsonResponse
    {
        return $this->json([
            'currentTime' => (new \DateTime())->format(\DateTime::ATOM),
        ]);
    }
}
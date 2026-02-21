<?php

namespace App\Controller\Api\Report;

use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report', name: 'api_report_')]
class GlobalController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    #[Route('/global-stats', name: 'global_stats', methods: ['GET'])]
    public function globalStats(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        return $this->json($this->reportService->globalStats($from, $to));
    }

    #[Route('/nonperiodic/employees-distribution', name: 'nonperiodic_employees_distribution', methods: ['GET'])]
    public function employeesDistribution(): JsonResponse
    {
        return $this->json($this->reportService->employeesDistribution());
    }

    #[Route('/nonperiodic/low-stock-consumables', name: 'nonperiodic_low_stock', methods: ['GET'])]
    public function lowStockConsumables(): JsonResponse
    {
        return $this->json($this->reportService->lowStockConsumables());
    }
}
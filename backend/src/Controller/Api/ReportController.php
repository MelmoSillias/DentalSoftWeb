<?php

namespace App\Controller\Api;

use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReportController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
    ) {
    }

    #[Route('/api/reports/data', name: 'api_reports_data', methods: ['GET'])]
    public function getReportsData(Request $request): JsonResponse
    {
        $period = $request->query->get('period', 'month');
        $employeeId = $request->query->get('employeeId');
        $customStart = $request->query->get('start');
        $customEnd = $request->query->get('end');

        $data = $this->reportService->getReportsData($period, $customStart, $customEnd, $employeeId);

        return new JsonResponse($data);
    }
}
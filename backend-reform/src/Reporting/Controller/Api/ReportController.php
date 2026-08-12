<?php

namespace App\Reporting\Controller\Api;

use App\Reporting\Application\Query\GetReport\GetReportQuery;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReportController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    #[Route('/api/reports/data', name: 'api_reports_data', methods: ['GET'])]
    public function getReportsData(Request $request): JsonResponse
    {
        $period = $request->query->get('period', 'month');
        $employeeId = $request->query->get('employeeId');
        $customStart = $request->query->get('start');
        $customEnd = $request->query->get('end');

        $data = $this->queryBus->ask(new GetReportQuery(
            period: (string) $period,
            start: $customStart !== null ? (string) $customStart : null,
            end: $customEnd !== null ? (string) $customEnd : null,
            employeeId: $employeeId,
        ));

        return new JsonResponse($data);
    }
}
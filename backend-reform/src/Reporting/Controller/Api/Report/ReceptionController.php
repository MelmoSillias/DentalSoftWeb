<?php

namespace App\Reporting\Controller\Api\Report;

use App\Reporting\Application\Query\GetReceptionStats\GetReceptionStatsQuery;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report', name: 'api_report_')]
class ReceptionController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {}

    #[Route('/reception-stats', name: 'api_report_receptionniste', methods: ['GET'])]
    public function getReceptionDashboard(Request $request): JsonResponse
    {
        $date = $request->query->get('date', (new \DateTimeImmutable())->format('Y-m-d'));
        $from = new \DateTimeImmutable($date . ' 00:00:00');
        $to = new \DateTimeImmutable($date . ' 23:59:59');

        return $this->json($this->queryBus->ask(new GetReceptionStatsQuery($from, $to)));
    }
}

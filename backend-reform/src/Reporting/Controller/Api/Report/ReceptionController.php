<?php

namespace App\Reporting\Controller\Api\Report;

use App\Focus\Service\DashboardService;
use App\Reporting\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report', name: 'api_report_')]
class ReceptionController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
        private DashboardService $dashboardService,
    ) {}

    #[Route('/reception-stats', name: 'api_report_receptionniste', methods: ['GET'])]
    public function getReceptionDashboard(Request $request): JsonResponse
    {
        $date = $request->query->get('date', (new \DateTimeImmutable())->format('Y-m-d'));
        $from = new \DateTimeImmutable($date . ' 00:00:00');
        $to   = new \DateTimeImmutable($date . ' 23:59:59');

        $cards       = $this->dashboardService->getReceptionCards($from, $to);
        $globalStats = $this->reportService->globalStats($date, $date);

        return $this->json([
            'newPatients'           => $cards['patients']['new'] ?? 0,
            'totalConsultations'    => $cards['consultations']['total'] ?? 0,
            'pendingConsultations'  => $cards['pendingConsultations']['total'] ?? 0,
            'totalAppointments'     => $cards['appointments']['pending'] ?? 0,
            'absentAppointments'    => $cards['appointments']['cancelled'] ?? 0,
            'paidInvoices'          => $cards['consultations']['paid'] ?? 0,
            'cashRevenue'           => $cards['cash']['total'] ?? 0,
            'totalRevenue'          => $globalStats['revenueTotal'] ?? 0,
        ]);
    }
}
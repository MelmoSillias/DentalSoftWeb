<?php

namespace App\Reporting\Controller\Api\Report;

use App\Reporting\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/report', name: 'api_report_')]
class PeriodicController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    #[Route('/periodic/consultations', name: 'periodic_consultations', methods: ['GET'])]
    public function periodicConsultations(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;
        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD.'],
                400
            );
        }
        return $this->json($this->reportService->periodicConsultations($fromDate, $toDate));
    }

    #[Route('/periodic/appointments', name: 'periodic_appointments', methods: ['GET'])]
    public function periodicAppointments(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;
        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD.'],
                400
            );
        }
        return $this->json($this->reportService->periodicAppointments($fromDate, $toDate));
    }

    #[Route('/periodic/room-usage', name: 'periodic_room_usage', methods: ['GET'])]
    public function periodicRoomUsage(Request $request): JsonResponse
    {
        return $this->json(['usage' => [], 'topRoom' => '']);
    }

    #[Route('/periodic/payment-balances', name: 'periodic_payment_balances', methods: ['GET'])]
    public function periodicPaymentBalances(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');

        $globalStats = $this->reportService->globalStats($from, $to);
        $balances = [];
        foreach ($globalStats['capitalBreakdown'] ?? [] as $mode => $balance) {
            $balances[] = ['mode' => $mode, 'balance' => round((float) $balance)];
        }

        return $this->json($balances);
    }

    #[Route('/periodic/payment-frequency', name: 'periodic_payment_frequency', methods: ['GET'])]
    public function periodicPaymentFrequency(Request $request): JsonResponse
    {
        return $this->json(['frequency' => [], 'topMode' => '']);
    }

    #[Route('/periodic/acts-stats', name: 'periodic_acts_stats', methods: ['GET'])]
    public function periodicActsStats(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate = $to ? \DateTime::createFromFormat('Y-m-d', $to) : null;
        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }

        if ($fromDate) {
            $fromDate->setTime(0, 0, 0);
        }
        if ($toDate) {
            $toDate->setTime(23, 59, 59);
        }

        return $this->json($this->reportService->periodicActsStats($fromDate, $toDate));
    }

    #[Route('/periodic/doctor-reports', name: 'periodic_doctor_reports', methods: ['GET'])]
    public function periodicDoctorReports(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        // Si "from" ou "to" est null, on prend depuis 2020-01-01 jusqu'à aujourd'hui
        if (!$from) {
            $from = '2020-01-01';
        }
        if (!$to) {
            $to = (new \DateTimeImmutable())->format('Y-m-d');
        }

        /** @var \DateTimeInterface $fromDate */
        $fromDate = new \DateTimeImmutable($from. ' 00:00:00');
        /** @var \DateTimeInterface $toDate */
        $toDate = new \DateTimeImmutable($to. ' 23:59:59');

        $stats = $this->reportService->periodicDoctorReports($fromDate, $toDate);

        return $this->json([
            'period' => [
                'from' => $fromDate->format('d/m/Y'),
                'to' => $toDate->format('d/m/Y'),
            ],
            'kpi' => $stats['kpi'],
            'doctors' => $stats['doctors'],
        ]);
    }
}
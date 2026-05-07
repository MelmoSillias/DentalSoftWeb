<?php

namespace App\Controller\Api\Report;

use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report', name: 'api_report_')]
class PatientsController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    #[Route('/global/patients', name: 'global_stats_patients', methods: ['GET'])]
    public function globalPatients(): JsonResponse
    {
        return $this->json($this->reportService->globalPatients());
    }

    #[Route('/global/patient-referrals', name: 'global_patient_referrals', methods: ['GET'])]
    public function globalPatientReferrals(): JsonResponse
    {
        return $this->json($this->reportService->globalPatientReferrals());
    }

    #[Route('/periodic/patients', name: 'periodic_patients', methods: ['GET'])]
    public function periodicPatients(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');

        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;

        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD for both "from" and "to".'],
                400
            );
        }
        return $this->json($this->reportService->periodicPatients($fromDate, $toDate));
    }
}
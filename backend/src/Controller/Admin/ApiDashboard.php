<?php
// src/Controller/APIDashboardController.php
namespace App\Controller\Admin;

use App\Config\sexeEnum;
use App\Entity\Consommable;
use App\Entity\Consultation;
use App\Entity\Employe;
use App\Entity\ModeDePaiement;
use App\Entity\PaiementDevis;
use App\Entity\Patient;
use App\Entity\Rdv;
use App\Repository\ActeMedicalRepository;
use App\Repository\ConsommableRepository;
use App\Repository\ConsultationRepository;
use App\Repository\DevisRepository;
use App\Repository\EmployeRepository;
use App\Repository\PatientRepository;
use App\Repository\RdvRepository;
use App\Repository\SalleRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\DashboardStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class ApiDashboard extends AbstractController
{
    public function __construct(
        private PatientRepository       $patientRepo,
        private TransactionRepository   $transactionRepo,
        private RdvRepository           $rdvRepo,
        private EmployeRepository      $employeRepo,
        private SalleRepository        $salleRepo,
        private ConsommableRepository   $consommableRepo,
        private UserRepository          $userRepo,
        private EntityManagerInterface $em,
        private ActeMedicalRepository  $acteRepo,
        private ConsultationRepository $consultRepo,
        private DashboardStatsService $dashboardStatsService,
    ) {}

    #[Route('/global-stats', name: 'global_stats', methods: ['GET'])]
    public function globalStats(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        return $this->json($this->dashboardStatsService->globalStats($from, $to));
    }

    #[Route('/nonperiodic/employees-distribution', name: 'nonperiodic_employees_distribution', methods: ['GET'])]
    public function employeesDistribution(): JsonResponse
    {
        return $this->json($this->dashboardStatsService->employeesDistribution());
    }

    #[Route('/nonperiodic/low-stock-consumables', name: 'nonperiodic_low_stock', methods: ['GET'])]
    public function lowStockConsumables(EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->json($this->dashboardStatsService->lowStockConsumables());
    }

    #[Route('/global/patients', name: 'global_stats_patients', methods: ['GET'])]
    public function globalPatients(): JsonResponse
    {
        return $this->json($this->dashboardStatsService->globalPatients());
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
        return $this->json($this->dashboardStatsService->periodicPatients($fromDate, $toDate));
    }


    #[Route(
        '/periodic/consultations',
        name: 'periodic_consultations',
        methods: ['GET']
    )]
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
        return $this->json($this->dashboardStatsService->periodicConsultations($fromDate, $toDate));
    }



    #[Route(
        '/periodic/appointments',
        name: 'periodic_appointments',
        methods: ['GET']
    )]
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
        return $this->json($this->dashboardStatsService->periodicAppointments($fromDate, $toDate));
    }

    #[Route('/periodic/room-usage', name: 'periodic_room_usage', methods: ['GET'])]
    public function periodicRoomUsage(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;
        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }
        return $this->json($this->dashboardStatsService->periodicRoomUsage($fromDate, $toDate));
    }

    #[Route('/periodic/payment-balances', name: 'periodic_payment_balances', methods: ['GET'])]
    public function periodicPaymentBalances(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from
            ? \DateTime::createFromFormat('Y-m-d', $from)->setTime(0, 0, 0)
            : null;
        $toDate   = $to
            ? \DateTime::createFromFormat('Y-m-d', $to)->setTime(23, 59, 59)
            : null;

        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD.'],
                400
            );
        }
        return $this->json($this->dashboardStatsService->periodicPaymentBalances($fromDate, $toDate));
    }


    #[Route('/periodic/payment-frequency', name: 'periodic_payment_frequency', methods: ['GET'])]
    public function periodicPaymentFrequency(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from
            ? \DateTime::createFromFormat('Y-m-d', $from)->setTime(0, 0, 0)
            : null;
        $toDate   = $to
            ? \DateTime::createFromFormat('Y-m-d', $to)->setTime(23, 59, 59)
            : null;

        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD.'],
                400
            );
        }
        return $this->json($this->dashboardStatsService->periodicPaymentFrequency($fromDate, $toDate));
    }

    #[Route(
        '/periodic/acts-stats',
        name: 'periodic_acts_stats',
        methods: ['GET']
    )]
    public function periodicActsStats(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from
            ? \DateTime::createFromFormat('Y-m-d', $from)->setTime(0, 0, 0)
            : null;
        $toDate   = $to
            ? \DateTime::createFromFormat('Y-m-d', $to)->setTime(23, 59, 59)
            : null;

        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(
                ['error' => 'Invalid date format. Use YYYY-MM-DD.'],
                400
            );
        }
        return $this->json($this->dashboardStatsService->periodicActsStats($fromDate, $toDate));
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

        /** @var Employe[] $doctors */
        $stats = $this->dashboardStatsService->periodicDoctorReports($fromDate, $toDate);

        return $this->json([
            'period' => [
                'from' => $fromDate->format('d/m/Y'),
                'to' => $toDate->format('d/m/Y'),
            ],
            'kpi' => $stats['kpi'],
            'doctors' => $stats['doctors'],
        ]);

    }

    #[Route('/medecin', name: 'api_medecin_dashboard', methods: ['GET'])]
    public function medecinDashboard(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        /** @var Employe $medecin */
        $medecin = $this->em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        if (!$medecin) {
            return $this->json(['error' => 'Aucun médecin trouvé'], 404);
        }

        $fromStr = $request->query->get('from');
        $toStr   = $request->query->get('to');

        $from = $fromStr ? new \DateTimeImmutable($fromStr . ' 00:00:00') : (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        $to   = $toStr   ? new \DateTimeImmutable($toStr   . ' 23:59:59') : (new \DateTimeImmutable())->setTime(23, 59, 59);

        $stats = $this->dashboardStatsService->medecinDashboard($medecin, $from, $to);

        return $this->json($stats);
    }

    #[Route('/reception-stats', name: 'api_dashboard_receptionniste', methods: ['GET'])]
    public function getReceptionDashboard(Request $request): JsonResponse
    {
        $date = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $dateStart = new \DateTime($date . ' 00:00:00');
        $dateEnd = new \DateTime($date . ' 23:59:59');
        return $this->json($this->dashboardStatsService->receptionDashboard($dateStart, $dateEnd));
    }
}

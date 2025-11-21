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
    ) {}

    #[Route('/global-stats', name: 'global_stats', methods: ['GET'])]
    public function globalStats(Request $request): JsonResponse
    {
        // 1. Récupération et validation de la période
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;
        // 2. Transactions sur la période
        $transactions = $this->transactionRepo
            ->findAll();
        // <-- à implémenter : récupération des Transaction entre fromDate et toDate

        // 3. Calcul du breakdown, total, inCash et revenue
        $capitalBreakdown = [];
        $capitalTotal     = 0;
        $inCash           = 0;
        $revenueTotal     = 0;

        foreach ($transactions as $tx) {
            $mode   = $tx->getModeDePaiement()->getType();
            $amount = $tx->getMontant();

            // 3a. Capital : on ajoute ou soustrait selon le type
            $signed = ($tx->getType() === 'Entrée') ? $amount : -$amount;
            $capitalTotal += $signed;

            // 3b. Breakdown par mode
            if (!isset($capitalBreakdown[$mode])) {
                $capitalBreakdown[$mode] = 0;
            }
            $capitalBreakdown[$mode] += $signed;

            // 3c. Part “espèces” uniquement
            if ($mode === 'Espèces') {
                $inCash += $signed;
            }

            // 3d. Chiffre d’affaires = sommes des paiements liés aux consultations
            if ($tx->getPaiementDevis() !== null) {
                $revenueTotal += $amount;
            }
        }

        // 4. Autres statistiques simples
        $patientsTotal      = $this->patientRepo->count([]);
        $employeesTotal     = $this->employeRepo->count([]);
        $fixedStats         = $this->employeRepo->findBy(['typeSalaire' => 'fixe']);
        $payrollFixed       = array_sum(array_map(fn($e) => $e->getValeurSalaire(), $fixedStats));
        $payrollFixedCount  = count($fixedStats);
        $consultRoomsCount  = $this->salleRepo->count([]);
        $consumablesCount   = $this->consommableRepo->count([]);
        $usersByRole        = [
            'administrateur'  => $this->userRepo->countByRole('ROLE_ADMIN'),
            'receptionniste'  => $this->userRepo->countByRole('ROLE_RECEPTIONNISTE'),
            'medecins'        => $this->userRepo->countByRole('ROLE_MEDECIN'),
        ];

        // 5. Construction de la réponse
        $data = [
            'patientsTotal'     => $patientsTotal,
            'capitalTotal'      => $capitalTotal,
            'capitalBreakdown'  => $capitalBreakdown,
            'inCash'            => $inCash,
            'revenueTotal'      => $revenueTotal,
            'employeesTotal'    => $employeesTotal,
            'payrollFixed'      => $payrollFixed,
            'payrollFixedCount' => $payrollFixedCount,
            'consultRoomsCount' => $consultRoomsCount,
            'consumablesCount'  => $consumablesCount,
            'usersByRole'       => $usersByRole,
        ];

        return $this->json($data);
    }

    #[Route('/nonperiodic/employees-distribution', name: 'nonperiodic_employees_distribution', methods: ['GET'])]
    public function employeesDistribution(): JsonResponse
    {
        $employeeRepository = $this->em->getRepository(Employe::class);

        // Query to get all distinct roles
        $roles = $employeeRepository->createQueryBuilder('e')
            ->select('DISTINCT e.type')
            ->getQuery()
            ->getSingleColumnResult();

        // Initialize the distribution array
        $distribution = [];

        // Count employees for each role
        foreach ($roles as $role) {
            $count = $employeeRepository->count(['type' => $role]);
            $distribution[$role . 's'] = $count; // Adding 's' to form plural
        }

        return $this->json($distribution);
    }

    #[Route('/nonperiodic/low-stock-consumables', name: 'nonperiodic_low_stock', methods: ['GET'])]
    public function lowStockConsumables(EntityManagerInterface $entityManager): JsonResponse
    {
        $consommableRepository = $entityManager->getRepository(Consommable::class);

        // Query to find consumables where the current quantity is below the lowValue threshold
        $lowStockItems = $consommableRepository->createQueryBuilder('c')
            ->where('c.quantity < c.lowValue')
            ->getQuery()
            ->getResult();

        // Format the data as required
        $items = array_map(function ($item) {
            return [
                'item' => $item->getNom(), // Assuming getNom() returns the name of the consumable
                'remaining' => $item->getQuantity() // Assuming getQuantity() returns the current stock
            ];
        }, $lowStockItems);

        return $this->json($items);
    }

    #[Route('/global/patients', name: 'global_stats_patients', methods: ['GET'])]
    public function globalPatients(): JsonResponse
    {
        $patients = $this->patientRepo->findAll();
        $total    = count($patients);

        $female   = 0;
        $male     = 0;
        $minors   = 0;
        $adults   = 0;
        $seniors  = 0;
        $sumAge   = 0;
        $countAge = 0;

        $today = new \DateTime();
        foreach ($patients as $p) {
            // 1. Sexe
            if ($p->getSexe() === 'Femme') {
                $female++;
            } elseif ($p->getSexe() === 'Homme') {
                $male++;
            }

            // 2. Âge
            $dob = $p->getDateNaissance();
            if ($dob instanceof \DateTimeInterface) {
                $age = $today->diff($dob)->y;
                $sumAge += $age;
                $countAge++;

                if ($age < 18) {
                    $minors++;
                } elseif ($age < 60) {
                    $adults++;
                } else {
                    $seniors++;
                }
            }
        }

        $averageAge = $countAge > 0
            ? round($sumAge / $countAge, 1)
            : null;

        $data = [
            'total'      => $total,
            'female'     => $female,
            'male'       => $male,
            'minors'     => $minors,
            'adults'     => $adults,
            'seniors'    => $seniors,
            'averageAge' => $averageAge,
        ];

        return $this->json($data);
    }

    #[Route('/periodic/patients', name: 'periodic_patients', methods: ['GET'])]
    public function periodicPatients(Request $request): JsonResponse
    {
        // Récupération et validation des paramètres de période
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

        // Comptage des nouveaux patients : inscription dans la période
        $qb = $this->patientRepo->createQueryBuilder('p');
        if ($fromDate) {
            $qb->andWhere('p.dateInscription >= :from')->setParameter('from', $fromDate->format('Y-m-d'));
        }
        if ($toDate) {
            $qb->andWhere('p.dateInscription <= :to')->setParameter('to', $toDate->format('Y-m-d'));
        }
        $newCount = (int) $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Comptage des patients de retour : première consultation dans la période,
        // mais inscription avant la période
        // Comptage des patients de retour : première consultation dans la période,
        // mais inscription avant la période
        $qbReturning = $this->patientRepo->createQueryBuilder('p')
            ->innerJoin('p.consultations', 'c')
            ->andWhere('c.CreatedAt >= :from')
            ->andWhere('c.CreatedAt <= :to')
            ->andWhere('p.dateInscription < :from')
            ->setParameter('from', $fromDate ? $fromDate->format('Y-m-d') : '1900-01-01')
            ->setParameter('to', $toDate ? $toDate->format('Y-m-d') : (new \DateTime())->format('Y-m-d'))
            ->groupBy('p.id')
            ->having('MIN(c.CreatedAt ) >= :from')
            ->having('MIN(c.CreatedAt ) <= :to');

        $returningCount = count($qbReturning->getQuery()->getResult());

        return $this->json([
            'newPatients'       => (int) $newCount,
            'returningPatients' => (int) $returningCount,
        ]);
    }


    #[Route(
        '/periodic/consultations',
        name: 'periodic_consultations',
        methods: ['GET']
    )]
    public function periodicConsultations(Request $request): JsonResponse
    {
        // … récupération & validation des dates … 
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

        // 1) Total des consultations
        $qbTotal = $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)');
        if ($fromDate) {
            $qbTotal->andWhere('c.CreatedAt >= :from')
                ->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbTotal->andWhere('c.CreatedAt <= :to')
                ->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $total = (int) $qbTotal->getQuery()->getSingleScalarResult();

        // 2) Nombre de consultations payantes
        //    => on fait un INNER JOIN sur paiementDevis plutôt que c.paiementDevis IS NOT NULL
        $qbPaid = $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.paiementDevis', 'pd'); // <— explicit join ici
        if ($fromDate) {
            $qbPaid->andWhere('c.CreatedAt >= :from')
                ->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbPaid->andWhere('c.CreatedAt <= :to')
                ->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $paid = (int) $qbPaid->getQuery()->getSingleScalarResult();

        // 3) Consultations gratuites
        $free = $total - $paid;

        $qbAmount = $this->transactionRepo->createQueryBuilder('t')
            ->select('SUM(t.montant) + SUM(CASE WHEN f.montant IS NOT NULL THEN f.montant ELSE 0 END)')
            ->leftJoin('t.paiementDevis', 'pd')
            ->leftJoin('pd.consultation', 'c')
            ->leftJoin('c.facture', 'f'); // jointure avec facture liée à la consultation

        if ($fromDate) {
            $qbAmount->andWhere('c.CreatedAt >= :from')
                ->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbAmount->andWhere('c.CreatedAt <= :to')
                ->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }

        $totalAmount = (int) $qbAmount->getQuery()->getSingleScalarResult();

        // 6) Montant moyen par consultation
        $averageAmount = $total > 0
            ? round($totalAmount / $total)
            : 0;

        // 7) Top 3 des actes les plus fréquents
        $qbActs = $this->acteRepo->createQueryBuilder('a')
            ->select('a.type AS acteType, COUNT(a.id) AS cnt')
            ->join('a.consultation', 'c')
            ->groupBy('a.type')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(3);
        if ($fromDate) {
            $qbActs->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbActs->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $topActsRows = $qbActs->getQuery()->getArrayResult();
        $topActs = array_map(fn($r) => $r['acteType'], $topActsRows);

        // 8) Réponse JSON
        return $this->json([
            'total'         => $total,
            'paid'          => $paid,
            'free'          => $free,
            'totalAmount'   => $totalAmount,
            'averageAmount' => $averageAmount,
            'topActs'       => $topActs,
        ]);
    }



    #[Route(
        '/periodic/appointments',
        name: 'periodic_appointments',
        methods: ['GET']
    )]
    public function periodicAppointments(Request $request): JsonResponse
    {
        // 1) Récupération et validation des dates
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

        // 2) Récupérer la liste des RDV dans l’intervalle
        $qb = $this->rdvRepo->createQueryBuilder('r');
        if ($fromDate) {
            $qb->andWhere('r.dateRdv >= :from')
                ->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qb->andWhere('r.dateRdv <= :to')
                ->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $appointments = $qb->getQuery()->getResult();

        // 3) Statuts
        $scheduled = count($appointments);
        $counts = [
            'pending'   => 0,  // statut 0
            'confirmed' => 0,  // statut 1
            'postponed' => 0,  // statut -1
            'cancelled' => 0,  // statut -2
        ];
        foreach ($appointments as $r) {
            switch ($r->getStatut()) {
                case 1:
                    $counts['confirmed']++;
                    break;
                case 0:
                    $counts['pending']++;
                    break;
                case -1:
                    $counts['postponed']++;
                    break;
                case -2:
                    $counts['cancelled']++;
                    break;
            }
        }

        // 4) Taux de confirmation
        $confirmationRate = $scheduled > 0
            ? round($counts['confirmed'] / $scheduled * 100)
            : 0;

        // 5) Délai moyen (en jours) entre création et date du RDV
        $sumDelay = 0;
        foreach ($appointments as $r) {
            $created = $r->getDateCreation(); // ou getDateCreation()
            $dateRdv = $r->getDateRdv();
            if ($created && $dateRdv) {
                $sumDelay += $created->diff($dateRdv)->days;
            }
        }
        $averageDelayDays = $scheduled > 0
            ? round($sumDelay / $scheduled)
            : 0;

        return $this->json([
            'scheduled'        => $scheduled,
            'confirmed'        => $counts['confirmed'],
            'pending'          => $counts['pending'],
            'postponed'        => $counts['postponed'],
            'cancelled'        => $counts['cancelled'],
            'confirmationRate' => $confirmationRate,
            'averageDelayDays' => $averageDelayDays,
        ]);
    }

    #[Route('/periodic/room-usage', name: 'periodic_room_usage', methods: ['GET'])]
    public function periodicRoomUsage(Request $request): JsonResponse
    {
        // 1) Récupération et validation des dates
        $from = $request->query->get('from');
        $to   = $request->query->get('to');
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;
        if (($from && !$fromDate) || ($to && !$toDate)) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }

        // 2) Récupérer le nombre de consultations par salle
        $qb = $this->consultRepo->createQueryBuilder('c')
            ->select('s.nom AS room, COUNT(c.id) AS cnt')
            ->join('c.salle', 's');
        if ($fromDate) {
            $qb->andWhere('c.CreatedAt >= :from')
                ->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qb->andWhere('c.CreatedAt <= :to')
                ->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $qb->groupBy('s.id');
        $rows = $qb->getQuery()->getArrayResult();

        // 3) Calcul des totaux et pourcentages
        $total = array_sum(array_map(fn($r) => (int)$r['cnt'], $rows));
        $usage = [];
        $topRoom    = null;
        $maxCount   = 0;

        foreach ($rows as $r) {
            $count   = (int) $r['cnt'];
            $percent = $total > 0 ? (int) round($count * 100 / $total) : 0;
            $usage[] = [
                'room'    => $r['room'],
                'count'   => $count,
                'percent' => $percent,
            ];
            if ($count > $maxCount) {
                $maxCount = $count;
                $topRoom  = $r['room'];
            }
        }

        return $this->json([
            'usage'   => $usage,
            'topRoom' => $topRoom,
        ]);
    }

    #[Route('/periodic/payment-balances', name: 'periodic_payment_balances', methods: ['GET'])]
    public function periodicPaymentBalances(Request $request): JsonResponse
    {
        // 1. Récupération et validation des dates
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

        // 2. Agréger le solde pour chaque mode de paiement
        $qb = $this->transactionRepo->createQueryBuilder('t')
            ->select(
                'm.libelle AS mode',
                // CASE WHEN t.type = 'Entrée' THEN montant ELSE -montant END
                "SUM(CASE WHEN t.type = :entry THEN t.montant ELSE -t.montant END) AS balance"
            )
            ->join('t.modeDePaiement', 'm')
            ->setParameter('entry', 'Entrée');

        if ($fromDate) {
            $qb->andWhere('t.dateTransaction >= :from')
                ->setParameter('from', $fromDate);
        }
        if ($toDate) {
            $qb->andWhere('t.dateTransaction <= :to')
                ->setParameter('to', $toDate);
        }

        $qb->groupBy('m.libelle');
        $rows = $qb->getQuery()->getArrayResult();

        // 3. Construire la réponse : liste dynamique des modes et soldes
        //    par exemple [ ['mode'=>'Espèces','balance'=>120000], ... ]
        return $this->json($rows);
    }


    #[Route('/periodic/payment-frequency', name: 'periodic_payment_frequency', methods: ['GET'])]
    public function periodicPaymentFrequency(Request $request): JsonResponse
    {
        // 1) Récupération et validation des paramètres from/to
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

        // 2) Récupérer le nombre de transactions par mode de paiement
        $qb = $this->transactionRepo->createQueryBuilder('t')
            ->select('m.libelle AS mode, COUNT(t.id) AS cnt')
            ->join('t.modeDePaiement', 'm');

        if ($fromDate) {
            $qb->andWhere('t.dateTransaction >= :from')
                ->setParameter('from', $fromDate);
        }
        if ($toDate) {
            $qb->andWhere('t.dateTransaction <= :to')
                ->setParameter('to', $toDate);
        }

        $qb->groupBy('m.libelle');
        $rows = $qb->getQuery()->getArrayResult();

        // 3) Calculer le total et les pourcentages
        $totalCount = array_sum(array_map(fn($r) => (int)$r['cnt'], $rows));
        $frequency  = [];
        $topMode    = null;
        $maxCount   = 0;

        foreach ($rows as $r) {
            $count   = (int) $r['cnt'];
            $percent = $totalCount > 0
                ? round($count * 100 / $totalCount)
                : 0;

            $frequency[] = [
                'mode'    => $r['mode'],
                'count'   => $count,
                'percent' => $percent,
            ];

            if ($count > $maxCount) {
                $maxCount = $count;
                $topMode  = $r['mode'];
            }
        }

        // 4) Retourner un objet JSON avec la liste et le meilleur mode
        return $this->json([
            'frequency' => $frequency,
            'topMode'   => $topMode,
        ]);
    }

    #[Route(
        '/periodic/acts-stats',
        name: 'periodic_acts_stats',
        methods: ['GET']
    )]
    public function periodicActsStats(Request $request): JsonResponse
    {
        // 1) Récupération et validation de la période
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

        // 2) Liste des types connus
        $knownTypes = [
            'Consultation',
            'Détartrage',
            'Extraction',
            'Remplissage',
            'Composite',
            'Amalgame',
            'Traitement de canal',
            'Traumatisme',
            'Couronne',
            'Blanchiment',
            'Radio',
            'Prothèse',
            'Orthodontie',
            'Chirurgie',
        ];
        // Initialisation à zéro
        $acts = array_fill_keys($knownTypes, 0);

        // 3) Comptage des actes groupés par type
        $qb = $this->acteRepo->createQueryBuilder('a')
            ->select('a.type AS actType, COUNT(a.id) AS cnt')
            ->join('a.consultation', 'c');

        if ($fromDate) {
            $qb->andWhere('c.CreatedAt >= :from')
                ->setParameter('from', $fromDate);
        }
        if ($toDate) {
            $qb->andWhere('c.CreatedAt <= :to')
                ->setParameter('to', $toDate);
        }

        $qb->groupBy('a.type');
        $rows = $qb->getQuery()->getArrayResult();

        // 4) On remplit uniquement les types existants
        foreach ($rows as $r) {
            if (in_array($r['actType'], $knownTypes, true)) {
                $acts[$r['actType']] = (int) $r['cnt'];
            }
        }

        // 5) Retour JSON avec tous les types connus
        return $this->json($acts);
    }


    #[Route('/periodic/doctor-reports', name: 'periodic_doctor_reports', methods: ['GET'])]
    public function periodicDoctorReports(Request $request, EntityManagerInterface $em): JsonResponse
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
        $doctors = $em->getRepository(Employe::class)->findBy(['type' => 'medecin']);

        /** @var Consultation[] $allConsultations */
        $allConsultations = $em->getRepository(Consultation::class)->findByDateRange($fromDate, $toDate);

        $doctorStats = [];
        $totalRevenue = 0.0;
        $totalSalaries = 0.0;

        foreach ($doctors as $doctor) {
            $consultations = array_filter($allConsultations, function (Consultation $c) use ($doctor) {
                return $c->getMedecin() && $c->getMedecin()->getId() === $doctor->getId();
            });

            $paid = 0;
            $free = 0;
            $apport = 0.0;
            $revenue = 0.0;
            $totalAmount = 0.0;
            $actsAmount = 0.0;
            $newPatients = 0;
            $returningPatients = 0;
            $patientIds = [];
            $consultationDetails = [];
            $actesList = [];
            $totalActs = 0;
            $paiements = [];
            $relicat_patient = 0;

            foreach ($consultations as $consult) {
                // Statistiques paiement
                if ($consult->getPaiementDevis()) {
                    $paid++;
                } else {
                    $free++;
                }

                // Calcul des montants
                $consultAmount = 0;
                if ($consult->getFacture() && $consult->getFacture()->getMontant()) {
                    $fact = $consult->getFacture();
                    $consultAmount = $consult->getFacture()->getMontant();
                    $apport += $consultAmount;
                    $totalAmount += $consultAmount;
                    $paiements[] = [
                        'date' => $consult->getFacture()->getDate()?->format('Y-m-d H:i'),
                        'medecin' => $doctor->getFullName(),
                        'patient' => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                        'telephone' => $consult->getPatient()?->getTelephone() ?? '-- -- -- --',
                        'montant' => $fact->getMontant() - $fact->getReste() ?? null,
                        'pour' => 'Soins'
                    ];
                }

                $paiementDevis = $consult->getPaiementDevis();
                if ($paiementDevis && $paiementDevis->getMontant()) {
                    $consultAmount += $paiementDevis->getMontant();
                    $apport += $paiementDevis->getMontant();
                    $totalAmount += $paiementDevis->getMontant();
                    $paiements[] = [
                        'date' => $consult->getCreatedAt()?->format('Y-m-d H:i'),
                        'medecin' => $doctor->getFullName(),
                        'patient' => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                        'telephone' => $consult->getPatient()?->getTelephone() ?? '-- -- -- --',
                        'montant' => $consult->getPaiementDevis()->getMontant() ?? null,
                        'pour' => 'Consultation'
                    ];
                }

                // Nouveaux vs patients fidélisés
                $patientId = $consult->getPatient()->getId();
                if (!in_array($patientId, $patientIds)) {
                    $newPatients++;
                    $patientIds[] = $patientId;
                } else {
                    $returningPatients++;
                }

                // Détails des consultations
                $consultationDetails[] = [
                    'date' => $consult->getCreatedAt()->format('d/m/Y'),
                    'patient' => $consult->getPatient()->getFullName(),
                    'type' => $consult->getNoteSeance(),
                    'amount' => $consultAmount,
                    'paid' => $consult->getPaiementDevis() !== null
                ];

                // Actes effectués
                if ($consult->getFacture()) {
                    foreach ($consult->getFacture()->getContenus() as $acte) {
                        $totalActs++;
                        $actAmount = $acte->getMontantTotal();
                        $actsAmount += $actAmount;

                        $actesList[] = [
                            'date' => $consult->getCreatedAt()->format('d/m/Y'),
                            'patient' => $consult->getPatient()->getFullName(),
                            'type' => $acte->getDesignation(),
                            'montant' => $actAmount,
                        ];
                    }
                    $relicat_patient += $consult->getFacture()->getReste();
                }
            }

            $paiementsPeriode = [];

            // 1) Paiements de tickets de consultation (PaiementDevis sans facture)
            $paiementsConsultations = $em->createQueryBuilder()
                ->select('pd')
                ->from(PaiementDevis::class, 'pd')
                ->join('pd.consultation', 'c')
                ->where('c.medecin = :doctor')
                ->andWhere('pd.date BETWEEN :from AND :to')
                ->setParameter('doctor', $doctor)
                ->setParameter('from', $fromDate)
                ->setParameter('to', $toDate)
                ->getQuery()
                ->getResult();

            foreach ($paiementsConsultations as $pay) {
                $consult = $pay->getConsultation();
                $patient = $consult->getPatient();
                $paiementsPeriode[] = [
                    'date' => $pay->getDate()->format('Y-m-d H:i'),
                    'medecin' => $doctor->getFullName(),
                    'patient' => $patient?->getFullName() ?? 'Inconnu',
                    'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                    'description' => 'Consultation',
                    'montant_total' => $pay->getMontant(),
                    'montant_paye' => $pay->getMontant(),
                    'reste' => 0,
                ];
                $revenue += $pay->getMontant();
            }

            // 2) Paiements de factures (PaiementDevis lié à Facture)
            $paiementsFactures = $em->createQueryBuilder()
                ->select('pd','f','c','c2')
                ->from(PaiementDevis::class, 'pd')
                ->leftJoin('pd.devis', 'f')
                ->leftJoin('f.consultation', 'c')
                // fallback : trouver une consultation ayant la même fiche que le devis (si mapping fiche existe)
                ->leftJoin(Consultation::class, 'c2', 'WITH', 'c2.fiche = f.fiche')
                ->where('(c.medecin = :doctor OR c2.medecin = :doctor)')
                ->andWhere('pd.date BETWEEN :from AND :to')
                ->setParameter('doctor', $doctor)
                ->setParameter('from', $fromDate)
                ->setParameter('to', $toDate)
                ->getQuery()
                ->getResult();

            foreach ($paiementsFactures as $pay) {
                $facture = $pay->getDevis();

                // essayer d'obtenir la consultation liée : d'abord via la relation, sinon recherche par fiche
                $consult = $facture?->getConsultation();
                if (!$consult && $facture?->getFiche()) {
                    $consult = $em->getRepository(Consultation::class)->findOneBy(['fiche' => $facture->getFiche()]);
                }

                $patient = $consult?->getPatient();

                // Concaténation des désignations d'actes
                $descriptions = [];
                if ($facture) {
                    foreach ($facture->getContenus() as $acte) {
                        $descriptions[] = $acte->getDesignation();
                    }
                }

                $paiementsPeriode[] = [
                    'date' => $pay->getDate()?->format('Y-m-d H:i'),
                    'medecin' => $doctor->getFullName(),
                    'patient' => $patient?->getFullName() ?? 'Inconnu',
                    'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                    'description' => implode(', ', $descriptions),
                    'montant_total' => $facture?->getMontant(),
                    'montant_paye' => $pay->getMontant(),
                    'reste' => $facture?->getReste(),
                ];
                $revenue += $pay->getMontant();
            }

            usort($paiementsPeriode, function ($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });

            // Calcul du salaire
            $salary = 0.0;
            if ($doctor->getTypeSalaire() === 'pourcentage') {
                $salary = ($doctor->getValeurSalaire() / 100) * $revenue;
            } else {
                $salary = $doctor->getValeurSalaire();
            }

            // Calcul des moyennes
            $avgAmount = count($consultations) > 0 ? $totalAmount / count($consultations) : 0;
            $avgAct = $totalActs > 0 ? $actsAmount / $totalActs : 0;

            $totalRevenue += $apport;
            $totalSalaries += $salary;

            $doctorStats[] = [
                'id' => $doctor->getId(),
                'name' => $doctor->getFullName(),
                'consultations' => count($consultations),
                'consultations_amount' => $paid * 5000,
                'total_amount' => $totalAmount,
                'avg_amount' => $avgAmount,
                'acts' => $totalActs,
                'acts_amount' => $actsAmount,
                'avg_act' => $avgAct,
                'new_patients' => $newPatients,
                'returning_patients' => $returningPatients,
                'revenue' => $revenue,
                'apport' => $apport,
                'reliquat' => $relicat_patient,
                'consultations_paid' => "$paid",
                'salary' => $salary,
                'consultation_details' => $consultationDetails,
                'actes' => $actesList,
                'paiements_period' => $paiementsPeriode,
                'paiements' => $paiements
            ];
        }

        return $this->json([
            'period' => [
                'from' => $fromDate->format('d/m/Y'),
                'to' => $toDate->format('d/m/Y')
            ],
            'kpi' => [
                'totalRevenue' => $totalRevenue,
                'afterFees' => $totalRevenue - $totalSalaries,
                'totalSalaries' => $totalSalaries,
                'totalConsultations' => count($allConsultations),
                'totalActs' => array_sum(array_column($doctorStats, 'acts')),
            ],
            'doctors' => $doctorStats,
        ]);
    }

    #[Route('/medecin', name: 'api_medecin_dashboard', methods: ['GET'])]
    public function medecinDashboard(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        /** @var Employe $medecin */
        $medecin = $em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        if (!$medecin) {
            return $this->json(['error' => 'Aucun médecin trouvé'], 404);
        }

        // Période sélectionnée (optionnelle)
        $fromStr = $request->query->get('from');
        $toStr   = $request->query->get('to');

        $from = $fromStr ? new \DateTimeImmutable($fromStr . ' 00:00:00') : (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        $to   = $toStr   ? new \DateTimeImmutable($toStr   . ' 23:59:59') : (new \DateTimeImmutable())->setTime(23, 59, 59);

        // 1. Patients ayant eu une consultation avec ce médecin
        $consultations = $em->getRepository(Consultation::class)->findBy(['medecin' => $medecin]);
        $patientsFromConsultations = array_map(fn($c) => $c->getPatient(), $consultations);

        // 2. Patients ayant un rendez-vous avec ce médecin
        $rdvs = $em->getRepository(Rdv::class)->findBy(['medecin' => $medecin]);
        $patientsFromRdvs = array_map(fn($r) => $r->getPatient(), $rdvs);

        // 3. Fusionner les deux listes de patients et éliminer les doublons
        $patients = array_unique(array_merge($patientsFromConsultations, $patientsFromRdvs), SORT_REGULAR);

        /** @var Consultation[] $consultations */
        $consultations = $em->getRepository(Consultation::class)->findBy([
            'medecin' => $medecin,
        ]);

        $consultationsPeriode = array_filter($consultations, function ($c) use ($from, $to) {
            return $c->getCreatedAt() >= $from && $c->getCreatedAt() <= $to;
        });

        $paidConsults = 0;
        $freeConsults = 0;
        $apport = 0.0;
        $revenue = 0.0;
        
        $actes = [];
        $paiements = [];

        foreach ($consultationsPeriode as $consult) {
            if ($consult->getFacture()) {
                $paidConsults++;
            } else {
                $freeConsults++;
            }

            if ($facture = $consult->getFacture()) {
                $apport += $facture->getMontant();
                $paiements[] = [
                    'date' => $facture->getDate()?->format('Y-m-d H:i'),
                    'medecin' => $medecin->getFullName(),
                    'patient' => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                    'telephone' => $consult->getPatient()?->getTelephone() ?? '-- -- -- --',
                    'montant' => $consult->getCreatedAt()?->format('Y-m-d H:i') ?? null,
                    'pour' => 'Soins'
                ];
            }

            if ($p = $consult->getPaiementDevis()) {
                $apport += $p->getMontant();
                $paiements[] = [
                    'date' => $consult->getCreatedAt()?->format('Y-m-d H:i'),
                    'medecin' => $medecin->getFullName(),
                    'patient' => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                    'telephone' => $consult->getPatient()?->getTelephone() ?? '-- -- -- --',
                    'montant' => $consult->getCreatedAt()?->format('Y-m-d H:i') ?? null,
                    'pour' => 'Consultation'
                ];
            }

            if ($consult->getFacture() !== null) {
                foreach ($consult->getFacture()->getContenus() as $acte) {
                    $actes[] = [
                        'nom' => $acte->getDesignation(),
                        'montant' => $acte->getMontant(),
                        'patient' => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                        'date' => $consult->getCreatedAt()?->format('Y-m-d H:i') ?? null
                    ];
                }
            }
        }

        $paiementsPeriode = [];

            // 1) Paiements de tickets de consultation (PaiementDevis sans facture)
            $paiementsConsultations = $em->createQueryBuilder()
                ->select('pd')
                ->from(PaiementDevis::class, 'pd')
                ->join('pd.consultation', 'c')
                ->where('c.medecin = :doctor')
                ->andWhere('pd.date BETWEEN :from AND :to')
                ->setParameter('doctor', $medecin)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();

            foreach ($paiementsConsultations as $pay) {
                $consult = $pay->getConsultation();
                $patient = $consult->getPatient();
                $paiementsPeriode[] = [
                    'date' => $pay->getDate()->format('Y-m-d H:i'),
                    'medecin' => $medecin->getFullName(),
                    'patient' => $patient?->getFullName() ?? 'Inconnu',
                    'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                    'description' => 'Consultation',
                    'montant_total' => $pay->getMontant(),
                    'montant_paye' => $pay->getMontant(),
                    'reste' => 0,
                ];
                $revenue += $pay->getMontant();
            }

            // 2) Paiements de factures (PaiementDevis lié à Facture)
            $paiementsFactures = $em->createQueryBuilder()
                ->select('pd','f','c','c2')
                ->from(PaiementDevis::class, 'pd')
                ->leftJoin('pd.devis', 'f')
                ->leftJoin('f.consultation', 'c')
                // fallback : trouver une consultation ayant la même fiche que le devis (si mapping fiche existe)
                ->leftJoin(Consultation::class, 'c2', 'WITH', 'c2.fiche = f.fiche')
                ->where('(c.medecin = :doctor OR c2.medecin = :doctor)')
                ->andWhere('pd.date BETWEEN :from AND :to')
                ->setParameter('doctor', $medecin)
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();

            foreach ($paiementsFactures as $pay) {
                $facture = $pay->getDevis();

                // essayer d'obtenir la consultation liée : d'abord via la relation, sinon recherche par fiche
                $consult = $facture?->getConsultation();
                if (!$consult && $facture?->getFiche()) {
                    $consult = $em->getRepository(Consultation::class)->findOneBy(['fiche' => $facture->getFiche()]);
                }

                $patient = $consult?->getPatient();

                // Concaténation des désignations d'actes
                $descriptions = [];
                if ($facture) {
                    foreach ($facture->getContenus() as $acte) {
                        $descriptions[] = $acte->getDesignation();
                    }
                }

                $paiementsPeriode[] = [
                    'date' => $pay->getDate()?->format('Y-m-d H:i'),
                    'medecin' => $medecin->getFullName(),
                    'patient' => $patient?->getFullName() ?? 'Inconnu',
                    'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                    'description' => implode(', ', $descriptions),
                    'montant_total' => $facture?->getMontant(),
                    'montant_paye' => $pay->getMontant(),
                    'reste' => $facture?->getReste(),
                ];
                $revenue += $pay->getMontant();
            }

            usort($paiementsPeriode, function ($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });

        // RDV du jour
        $today = new \DateTimeImmutable();
        $startToday = $today->setTime(0, 0, 0);
        $endToday = $today->setTime(23, 59, 59);

        $rdvToday = $em->getRepository(Rdv::class)->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.medecin = :medecin')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('medecin', $medecin)
            ->setParameter('start', $startToday)
            ->setParameter('end', $endToday)
            ->getQuery()
            ->getSingleScalarResult();

        // RDV stats période
        $rdvQb = $em->getRepository(Rdv::class)->createQueryBuilder('r')
            ->where('r.medecin = :medecin')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $rdvs = $rdvQb->getQuery()->getResult();

        $rdvStats = [
            'rdvPlanifies' => count($rdvs),
            'rdvEnAttente' => 0,
            'rdvValides' => 0,
            'rdvReportes' => 0,
            'rdvAnnules' => 0,
        ];

        foreach ($rdvs as $r) {
            switch ($r->getStatut()) {
                case 0:
                    $rdvStats['rdvEnAttente']++;
                    break;
                case 1:
                    $rdvStats['rdvValides']++;
                    break;
                case -1:
                    $rdvStats['rdvReportes']++;
                    break;
                case -2:
                    $rdvStats['rdvAnnules']++;
                    break;
            }
        }

        return $this->json([
            'nom' => $medecin->getNom(),
            'prenom' => $medecin->getPrenom(),
            'matricule' => $medecin->getMatricule(),
            'fonction' => $medecin->getFonction(),
            'email' => $medecin->getEmail(),
            'telephone' => $medecin->getTelephone(),
            'type' => $medecin->getType(),
            'dateEmbauche' => $medecin->getDateEmbauche()?->format('Y-m-d'),
            'typeSalaire' => $medecin->getTypeSalaire(),
            'valeurSalaire' => $medecin->getValeurSalaire(),
            'typeContrat' => $medecin->getTypeContrat(),
            'dureeContrat' => $medecin->getDureeContrat(),
            'joursTravailles' => $medecin->getComingDaysInWeek(),
           

            'stats' => [
                'patientsTotal' => count($patients),
                'totalConsultations' => count($consultations),
                'consultationsEnAttente' => count(array_filter($consultations, fn($c) => $c->getStatut() === 0)),
                'rdvJour' => $rdvToday,
            ],
            'period' => [
                'freeConsultations' => $freeConsults,
                'paidConsultations' => $paidConsults,
                'apportTotal' => $apport,
                'actesMedicaux' => $actes, 'revenue' => $revenue,
                'apport' => $apport,
                'paiements_period' => $paiementsPeriode,
            ] + $rdvStats
        ]);
    }

    #[Route('/reception-stats', name: 'api_dashboard_receptionniste', methods: ['GET'])]
    public function getReceptionDashboard(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $date = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $dateStart = new \DateTime($date . ' 00:00:00');
        $dateEnd = new \DateTime($date . ' 23:59:59');

        // 1. Nouveaux patients
        $newPatients = $em->createQuery("
        SELECT COUNT(p.id) FROM App\Entity\Patient p 
        WHERE p.dateInscription BETWEEN :start AND :end
    ")->setParameters(['start' => $dateStart, 'end' => $dateEnd])->getSingleScalarResult();

        // 2. Consultations totales et en attente
        $totalConsultations = $em->createQuery("
        SELECT COUNT(c.id) FROM App\Entity\Consultation c 
        WHERE c.CreatedAt BETWEEN :start AND :end
    ")->setParameters(['start' => $dateStart, 'end' => $dateEnd])->getSingleScalarResult();

        $pendingConsultations = $em->createQuery("
        SELECT COUNT(c.id) FROM App\Entity\Consultation c 
        WHERE c.CreatedAt BETWEEN :start AND :end AND c.statut = 0
    ")->setParameters(['start' => $dateStart, 'end' => $dateEnd])->getSingleScalarResult();

        // 3. RDV (tous + statut)
        $rdvStats = $em->createQuery("
        SELECT 
            COUNT(r.id) AS total,
            SUM(CASE WHEN r.statut = 0 THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN r.statut = 1 THEN 1 ELSE 0 END) AS confirmed,
            SUM(CASE WHEN r.statut = 2 THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN r.statut = 3 THEN 1 ELSE 0 END) AS postponed
        FROM App\Entity\Rdv r
        WHERE r.dateRdv BETWEEN :start AND :end
    ")->setParameters(['start' => $dateStart, 'end' => $dateEnd])->getSingleResult();

        $modeEspeces = $em->getRepository(ModeDePaiement::class)->find(0);

        $revenusEspeces = $em->createQuery("
        SELECT SUM(t.montant)
        FROM App\Entity\Transaction t
        WHERE t.dateTransaction BETWEEN :start AND :end
        AND t.modeDePaiement = :mode
        AND t.type = 'Entrée'
    ")->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->setParameter('mode', $modeEspeces)
            ->getSingleScalarResult();


        // 5. Revenus totaux
        $revenusTotaux = $em->createQuery("
    SELECT SUM(t.montant)
    FROM App\Entity\Transaction t
    WHERE t.dateTransaction BETWEEN :start AND :end
    AND t.type = 'Entrée'
")->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->getSingleScalarResult();


        return $this->json([
            'newPatients'           => (int) $newPatients,
            'consultations'         => (int) $totalConsultations,
            'pendingConsultations'  => (int) $pendingConsultations,
            'appointments' => [
                'total'     => (int) $rdvStats['total'],
                'pending'   => (int) $rdvStats['pending'],
                'confirmed' => (int) $rdvStats['confirmed'],
                'cancelled' => (int) $rdvStats['cancelled'],
                'postponed' => (int) $rdvStats['postponed'],
            ],
            'cashRevenue'  => (float) $revenusEspeces,
            'totalRevenue' => (float) $revenusTotaux,
        ]);
    }
}

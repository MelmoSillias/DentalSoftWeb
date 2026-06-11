<?php

namespace App\Reporting\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Facture;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\TransactionRepository;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ActeMedicalRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\IdentityAccess\Entity\Employe;
use App\Inventory\Entity\Consommable;
use App\Inventory\Repository\ConsommableRepository; 
use App\Patient\Repository\PatientRepository;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\Scheduling\Repository\SalleRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use DateTimeImmutable;
use DateTimeInterface;

class ReportService
{
    public function __construct(
        private PatientRepository $patientRepo,
        private TransactionRepository $transactionRepo,
        private RdvRepository $rdvRepo,
        private EmployeRepository $employeRepo,
        private SalleRepository $salleRepo,
        private ConsommableRepository $consommableRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private ActeMedicalRepository $acteRepo,
        private ConsultationRepository $consultRepo,
        private CacheInterface $cache,
    ) {}

    // ====================== HELPERS ======================

    private function remember(string $key, int $ttl, callable $builder): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($ttl, $builder) {
            $item->expiresAfter($ttl);
            return $builder();
        });
    }

    private function resolveReportRange(?string $period, ?string $customStart, ?string $customEnd): array
    {
        $now = new DateTimeImmutable();

        if ($period === 'custom' && $customStart && $customEnd) {
            return [
                new DateTimeImmutable($customStart),
                (new DateTimeImmutable($customEnd))->setTime(23, 59, 59),
            ];
        }

        return match ($period) {
            'today' => [$now->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
            'week'  => [$now->modify('-6 days')->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
            'year'  => [$now->modify('-11 months')->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
            default => [$now->modify('-29 days')->setTime(0, 0, 0), $now->setTime(23, 59, 59)], // month
        };
    }

    private function normalize(string $value): string
    {
        return str_replace(['é', 'è', 'ê', 'ë'], 'e', strtolower(trim($value)));
    }

    private function isRevenueTransactionType(?string $type): bool
    {
        return in_array($this->normalize((string) $type), ['entree', 'revenu', 'revenue'], true);
    }

    private function isCashMode(?string $mode): bool
    {
        return in_array($this->normalize((string) $mode), ['especes', 'espece', 'cash'], true);
    }

    public function computeCashRevenueForPeriod(DateTimeInterface $from, DateTimeInterface $to): float
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->leftJoin('t.modeDePaiement', 'm')
            ->andWhere('t.dateTransaction BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($transactions as $tx) {
            if (!$this->isRevenueTransactionType($tx->getType())) {
                continue;
            }

            $modeType = (string) ($tx->getModeDePaiement()?->getType() ?? '');
            if ($this->isCashMode($modeType)) {
                $total += (float) ($tx->getMontant() ?? 0);
            }
        }

        return $total;
    }

    public function getReceptionStats(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $cacheKey = 'report.reception.stats.' . $from->format('Ymd') . '.' . $to->format('Ymd');

        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $fromMutable = \DateTime::createFromImmutable($from);
            $toMutable = \DateTime::createFromImmutable($to);

            $globalStats = $this->globalStats($from->format('Y-m-d'), $to->format('Y-m-d'));
            $consultStats = $this->periodicConsultations($fromMutable, $toMutable);
            $appointmentStats = $this->periodicAppointments($fromMutable, $toMutable);
            $patientStats = $this->periodicPatients($fromMutable, $toMutable);

            $pendingConsultations = (int) $this->consultRepo->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->innerJoin('c.patient', 'cp')
                ->andWhere('c.statut = 0')
                ->andWhere('c.CreatedAt BETWEEN :from AND :to')
                ->andWhere('cp.deletedAt IS NULL')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getSingleScalarResult();

            $pendingAppointments = (int) $this->rdvRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->innerJoin('r.patient', 'rp')
                ->andWhere('r.statut = 0')
                ->andWhere('r.dateRdv BETWEEN :from AND :to')
                ->andWhere('rp.deletedAt IS NULL')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'newPatients' => $patientStats['newPatients'] ?? 0,
                'totalConsultations' => $consultStats['total'] ?? 0,
                'pendingConsultations' => $pendingConsultations,
                'totalAppointments' => $pendingAppointments,
                'absentAppointments' => $appointmentStats['cancelled'] ?? 0,
                'paidInvoices' => $consultStats['paid'] ?? 0,
                'cashRevenue' => round((float) ($globalStats['inCash'] ?? 0), 2),
                'totalRevenue' => round((float) ($globalStats['revenueTotal'] ?? 0), 2),
            ];
        });
    }

    private function signedAmount(Transaction $tx): float
    {
        $amount = (float) ($tx->getMontant() ?? 0);
        return $this->isRevenueTransactionType($tx->getType()) ? $amount : -$amount;
    }

    /**
     * Charge une seule fois toutes les données nécessaires pour une période
     */
    private function getPeriodData(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $cacheKey = 'report.period_data.' . $from->format('Ymd') . '.' . $to->format('Ymd');

        return $this->remember($cacheKey, 300, function () use ($from, $to) {
            // Consultations avec tous les joins nécessaires
            $consultations = $this->consultRepo->createQueryBuilder('c')
                ->leftJoin('c.patient', 'p')
                ->leftJoin('c.medecin', 'm')
                ->leftJoin('c.facture', 'f')
                ->leftJoin('c.factureAssurance', 'fa')
                ->leftJoin('c.actes', 'a')
                ->leftJoin('c.paiement', 'pay')
                ->andWhere('c.CreatedAt BETWEEN :from AND :to')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();

            // Transactions
            $transactions = $this->transactionRepo->createQueryBuilder('t')
                ->leftJoin('t.modeDePaiement', 'm')
                ->leftJoin('t.consultation', 'c')
                ->andWhere('t.dateTransaction BETWEEN :from AND :to')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();

            // Rendez-vous
            $rdvs = $this->rdvRepo->createQueryBuilder('r')
                ->leftJoin('r.patient', 'p')
                ->andWhere('r.dateRdv BETWEEN :from AND :to')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();

            return [
                'consultations' => $consultations,
                'transactions'  => $transactions,
                'rdvs'          => $rdvs,
            ];
        });
    }

    // ====================== RAPPORTS GÉNÉRAUX ======================

    public function globalStats(?string $from, ?string $to): array
    {
        $cacheKey = sprintf('report.globalStats.%s.%s', $from ?? 'none', $to ?? 'none');

        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $fromDate = $from ? DateTimeImmutable::createFromFormat('Y-m-d', $from)?->setTime(0, 0) : null;
            $toDate   = $to   ? DateTimeImmutable::createFromFormat('Y-m-d', $to)?->setTime(23, 59, 59) : null;

            $transactions = $this->transactionRepo->createQueryBuilder('t')
                ->leftJoin('t.modeDePaiement', 'm')
                ->where('t.dateTransaction BETWEEN :from AND :to')
                ->setParameter('from', $fromDate ?? new DateTimeImmutable('1900-01-01'))
                ->setParameter('to', $toDate ?? new DateTimeImmutable())
                ->getQuery()->getResult();

            $capitalTotal = $inCash = $revenueTotal = 0.0;
            $capitalBreakdown = [];

            foreach ($transactions as $tx) {
                $signed = $this->signedAmount($tx);
                $mode   = (string) ($tx->getModeDePaiement()?->getType() ?? 'Inconnu');

                $capitalTotal += $signed;
                $capitalBreakdown[$mode] = ($capitalBreakdown[$mode] ?? 0) + $signed;

                if ($this->isCashMode($mode) && $this->isRevenueTransactionType($tx->getType())) {
                    $inCash += (float) $tx->getMontant();
                }
                if ($this->isRevenueTransactionType($tx->getType())) {
                    $revenueTotal += (float) $tx->getMontant();
                }
            }

            return [
                'patientsTotal'     => $this->patientRepo->count(['deletedAt' => null]),
                'capitalTotal'      => $capitalTotal,
                'capitalBreakdown'  => $capitalBreakdown,
                'inCash'            => $inCash,
                'revenueTotal'      => $revenueTotal,
                'employeesTotal'    => $this->employeRepo->count([]),
                'payrollFixed'      => array_sum(array_column($this->employeRepo->findBy(['typeSalaire' => 'fixe']), 'valeurSalaire')),
                'payrollFixedCount' => count($this->employeRepo->findBy(['typeSalaire' => 'fixe'])),
                'consultRoomsCount' => $this->salleRepo->count([]),
                'consumablesCount'  => $this->consommableRepo->count([]),
                'usersByRole'       => [
                    'administrateur' => $this->userRepo->countByRole('ROLE_ADMIN'),
                    'receptionniste' => $this->userRepo->countByRole('ROLE_RECEPTIONNISTE'),
                    'medecins'       => $this->userRepo->countByRole('ROLE_MEDECIN'),
                ],
            ];
        });
    }

    public function employeesDistribution(): array
    {
        return $this->remember('report.employeesDistribution', 360, function () {
            $rows = $this->em->createQueryBuilder()
                ->select('e.type, COUNT(e.id) AS cnt')
                ->from(Employe::class, 'e')
                ->groupBy('e.type')
                ->getQuery()->getArrayResult();

            $distribution = [];
            foreach ($rows as $row) {
                $distribution[$row['type'] . 's'] = (int) $row['cnt'];
            }
            return $distribution;
        });
    }

    public function buildPatientsReport(): array
    {
        return $this->remember('report.patientsReport', 300, function () {
            $patients = $this->patientRepo->findBy(['deletedAt' => null]);
            $male = $female = $totalAge = $withBirth = 0;
            $ageGroups = ['<18' => 0, '18-30' => 0, '31-50' => 0, '51+' => 0];
            $regions = [];
            $today = new DateTimeImmutable();

            foreach ($patients as $patient) {
                if ($patient->getSexe() === 'Homme') $male++;
                elseif ($patient->getSexe() === 'Femme') $female++;

                $birth = $patient->getDateNaissance();
                if ($birth) {
                    $age = $today->diff($birth)->y;
                    $totalAge += $age;
                    $withBirth++;
                    if ($age < 18) $ageGroups['<18']++;
                    elseif ($age <= 30) $ageGroups['18-30']++;
                    elseif ($age <= 50) $ageGroups['31-50']++;
                    else $ageGroups['51+']++;
                }

                $address = trim((string) $patient->getAdresse());
                if ($address) {
                    $chunks = preg_split('/\s*,\s*/', $address);
                    $region = trim(end($chunks));
                    if ($region) {
                        $regions[$region] = ($regions[$region] ?? 0) + 1;
                    }
                }
            }

            arsort($regions);

            return [
                'male'       => $male,
                'female'     => $female,
                'ageGroups'  => $ageGroups,
                'averageAge' => $withBirth > 0 ? (int) round($totalAge / $withBirth) : 0,
                'regions'    => array_map(
                    fn($region, $count) => ['region' => $region, 'count' => $count],
                    array_keys($regions),
                    array_values($regions)
                ),
            ];
        });
    }

    public function globalPatients(): array
    {
        return $this->remember('report.globalPatients', 300, function () {
            $base   = $this->buildPatientsReport();
            $groups = $base['ageGroups'];

            return [
                'total'      => $this->patientRepo->count(['deletedAt' => null]),
                'male'       => $base['male'],
                'female'     => $base['female'],
                'minors'     => $groups['<18'] ?? 0,
                'adults'     => ($groups['18-30'] ?? 0) + ($groups['31-50'] ?? 0),
                'seniors'    => $groups['51+'] ?? 0,
                'averageAge' => $base['averageAge'] ?? 0,
            ];
        });
    }

    public function globalPatientReferrals(): array
    {
        return $this->remember('report.globalPatientReferrals', 300, function () {
            $rows = $this->patientRepo->createQueryBuilder('p')
                ->select('p.referencement AS source, COUNT(p.id) AS cnt')
                ->andWhere('p.deletedAt IS NULL')
                ->groupBy('p.referencement')
                ->orderBy('cnt', 'DESC')
                ->getQuery()
                ->getArrayResult();

            return array_map(fn($r) => [
                'source' => $r['source'] ?? 'Non renseigné',
                'count'  => (int) $r['cnt'],
            ], $rows);
        });
    }

    public function periodicPatients(?\DateTime $from, ?\DateTime $to): array
    {
        $cacheKey = 'report.periodicPatients.' . ($from?->format('Ymd') ?? '') . '.' . ($to?->format('Ymd') ?? '');

        return $this->remember($cacheKey, 180, function () use ($from, $to) {
            $qb = $this->patientRepo->createQueryBuilder('p')
                ->select('COUNT(p.id) AS cnt')
                ->andWhere('p.deletedAt IS NULL');

            if ($from) {
                $qb->andWhere('p.dateInscription >= :from')->setParameter('from', $from);
            }
            if ($to) {
                $qb->andWhere('p.dateInscription <= :to')->setParameter('to', $to);
            }

            $newPatients = (int) $qb->getQuery()->getSingleScalarResult();

            $returningQb = $this->consultRepo->createQueryBuilder('c')
                ->select('COUNT(DISTINCT p2.id)')
                ->join('c.patient', 'p2')
                ->andWhere('p2.deletedAt IS NULL');

            if ($from) {
                $returningQb
                    ->andWhere('c.CreatedAt >= :from')
                    ->andWhere('p2.dateInscription < :from')
                    ->setParameter('from', $from);
            }
            if ($to) {
                $returningQb->andWhere('c.CreatedAt <= :to')->setParameter('to', $to);
            }

            $returningPatients = (int) $returningQb->getQuery()->getSingleScalarResult();

            return [
                'newPatients'       => $newPatients,
                'returningPatients' => $returningPatients,
            ];
        });
    }

    public function buildRevenueTrend(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->andWhere('t.dateTransaction BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($transactions as $tx) {
            $date = $tx->getDateTransaction()?->format('Y-m-d');
            if (!$date) {
                continue;
            }

            $byDay[$date] = ($byDay[$date] ?? 0) + $this->signedAmount($tx);
        }

        ksort($byDay);

        return array_map(static fn(string $date, float $amount) => [
            'date' => $date,
            'amount' => round($amount),
        ], array_keys($byDay), array_values($byDay));
    }

    public function buildAppointmentsTrend(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select("DATE(COALESCE(r.dateRdv, c.CreatedAt)) AS date")
            ->addSelect("COUNT(DISTINCT r.id) AS appointments")
            ->addSelect("COUNT(DISTINCT c.id) AS consultations")
            ->from(Rdv::class, 'r')
            ->leftJoin(Consultation::class, 'c', 'WITH', 'DATE(r.dateRdv) = DATE(c.CreatedAt)')
            ->where('r.dateRdv BETWEEN :start AND :end OR c.CreatedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('date')
            ->orderBy('date')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($r) => [
            'date' => $r['date'],
            'appointments' => (int)$r['appointments'],
            'consultations' => (int)$r['consultations'],
        ], $rows);
    }

    public function periodicDoctorReports(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $cacheKey = 'report.doctor.reports.' . $from->format('Ymd') . '.' . $to->format('Ymd');

        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $periodData = $this->getPeriodData($from, $to);
            $doctors = $this->employeRepo->FindAllMedecin() ?? [];

            $doctorStats = [];
            $totalRevenue = $totalSalaries = 0.0;

            foreach ($doctors as $doctor) {
                $consults = array_filter($periodData['consultations'], fn(Consultation $c) =>
                    $c->getMedecin()?->getId() === $doctor->getId()
                );

                $stats = $this->computeDoctorStats($doctor, $consults, $from, $to, $periodData['transactions']);

                $totalRevenue += $stats['revenue'];
                $totalSalaries += $stats['salary'];

                $doctorStats[] = $stats;
            }

            return [
                'kpi' => [
                    'totalRevenue' => $totalRevenue,
                    'afterFees' => $totalRevenue - $totalSalaries,
                    'totalSalaries' => $totalSalaries,
                    'totalConsultations' => count($periodData['consultations']),
                ],
                'doctors' => $doctorStats,
            ];
        });
    }

    private function computeDoctorStats(
        Employe $doctor,
        array $consultations,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $allTransactions
    ): array {
        $paid = $free = 0;
        $apport = $revenue = $totalAmount = $actsAmount = $relicat = 0.0;
        $newPatients = $returningPatients = $totalActs = 0;
        $patientIds = [];
        $consultationDetails = [];
        $actesList = [];
        $paiementsPeriode = [];

        /** @var Consultation $consult */
        foreach ($consultations as $consult) {
            /** @var Facture|null $facture */
            $facture = $consult->getFacture();
            /** @var FactureAssurance|null $factureAssurance */
            $factureAssurance = $consult->getFactureAssurance();
            $factureAssuranceTotals = $factureAssurance?->computeTotals() ?? [];

            $consultTotalAmount = $facture !== null
                ? (float)($facture->computeMontantsFromConsultation()['montantTotal'] + $consult->getPaiement()?->getMontant() ?? 0)
                : (float)($factureAssuranceTotals['montantTotal'] ?? 0);

            $patientPaid = (float)($consult->getPaiement()?->getMontant() ?? 0);
            $remainingPatient = $facture !== null
                ? (float)($facture->computeMontantsFromConsultation()['restePatient'] ?? $consultTotalAmount)
                : max(0, (float)($factureAssuranceTotals['montantPatient'] ?? 0) - $patientPaid);

            $isInsuranceSettled = !$factureAssurance || $factureAssurance->getInsuranceStatus() === 'recouvre';

            if ($facture !== null && $consult->getPaiement()?->getMontant() === 0 || $factureAssurance !== null && $factureAssurance->getConsultationAmount() === 0) {
                $free++;
            } else {
                $paid++;
            }

            $apport += $consultTotalAmount;
            $totalAmount += $consultTotalAmount;
            $relicat += $remainingPatient;

            $patientId = $consult->getPatient()?->getId();
            if ($patientId) {
                if (!in_array($patientId, $patientIds, true)) {
                    $newPatients++;
                    $patientIds[] = $patientId;
                } else {
                    $returningPatients++;
                }
            }

            // Actes
            $consultActesTotal = 0.0;
            $actLabels = [];
            foreach ($consult->getActes() as $acte) {
                $totalActs++;
                $actAmount = (float)(($acte->getPrix() ?? 0) * max(1, (int)($acte->getQuantite() ?? 1)));
                $actsAmount += $actAmount;
                $consultActesTotal += $actAmount;
                $totalAmount += $actAmount;
                $label = trim((string)($acte->getType() ?? ''));
                if ($label === '') {
                    $label = trim((string)($acte->getDescription() ?? ''));
                }
                if ($label !== '') {
                    $actLabels[] = $label;
                }
            }

            // Détails
            if ($facture || $factureAssurance) {
                $hasPaiement = $consult->getPaiement() !== null;
                $parts = $hasPaiement ? ['Consultation'] : [];
                foreach ($actLabels as $label) {
                    $parts[] = $label;
                }
                $actesList[] = [
                    'date'        => $consult->getCreatedAt()?->format('d/m/Y'),
                    'patient'     => $consult->getPatient()?->getFullName() ?? 'Inconnu',
                    'description' => implode(' + ', $parts) ?: 'Acte médical',
                    'montant'     => $consultTotalAmount,
                ];
            }
        }

        // Revenue via paiements (transactions filtrées par médecin)
        $doctorTransactions = array_filter($allTransactions, fn(Transaction $t) =>
            $t->getConsultation()?->getMedecin()?->getId() === $doctor->getId()
        );

        foreach ($doctorTransactions as $tx) {
            if ($this->isRevenueTransactionType($tx->getType())) {
                $revenue += (float)$tx->getMontant();
            }
        }

        // Calcul salaire
        $salary = match ($doctor->getTypeSalaire()) {
            'pourcentage' => ($doctor->getValeurSalaire() ?? 0) / 100 * $revenue,
            'non_defini'  => 0.0,
            default       => (float)$doctor->getValeurSalaire(),
        };

        return [
            'id' => $doctor->getId(),
            'name' => $doctor->getFullName(),
            'consultations' => count($consultations),
            'new_patients' => $newPatients,
            'returning_patients' => $returningPatients,
            'revenue' => $revenue,
            'apport' => $apport,
            'reliquat' => $relicat,
            'consultations_paid' => $paid,
            'salary' => $salary,
            'actes' => $actesList,
            // Ajoute d'autres champs si nécessaire
        ];
    }

    // ====================== AUTRES MÉTHODES (exemples optimisés) ======================

    public function periodicConsultations(?DateTime $from, ?DateTime $to): array
    {
        $cacheKey = 'report.periodicConsultations.' . ($from?->format('Ymd') ?? '') . '.' . ($to?->format('Ymd') ?? '');

        return $this->remember($cacheKey, 180, function () use ($from, $to) {
            $qb = $this->consultRepo->createQueryBuilder('c')
                ->select('COUNT(c.id) AS total')
                ->addSelect('SUM(CASE WHEN pay.id IS NOT NULL OR f.id IS NOT NULL THEN 1 ELSE 0 END) AS paid')
                ->innerJoin('c.patient', 'cp')
                ->leftJoin('c.paiement', 'pay')
                ->leftJoin('c.facture', 'f')
                ->andWhere('cp.deletedAt IS NULL');

            if ($from) $qb->andWhere('c.CreatedAt >= :from')->setParameter('from', $from);
            if ($to)   $qb->andWhere('c.CreatedAt <= :to')->setParameter('to', $to);

            $result = $qb->getQuery()->getSingleResult();

            $total = (int)$result['total'];
            $paid = (int)$result['paid'];

            return [
                'total' => $total,
                'paid' => $paid,
                'free' => $total - $paid,
                // Ajoute totalAmount, averageAmount, topActs selon besoin
            ];
        });
    }

    public function periodicAppointments(?DateTime $from, ?DateTime $to): array
    {
        $cacheKey = 'report.periodicAppointments.' . ($from?->format('Ymd') ?? '') . '.' . ($to?->format('Ymd') ?? '');

        return $this->remember($cacheKey, 180, function () use ($from, $to) {
            $qb = $this->rdvRepo->createQueryBuilder('r')
                ->select('COUNT(r.id) AS total')
                ->addSelect('SUM(CASE WHEN r.statut = 0 THEN 1 ELSE 0 END) AS pending')
                ->addSelect('SUM(CASE WHEN r.statut = 1 THEN 1 ELSE 0 END) AS confirmed')
                ->addSelect('SUM(CASE WHEN r.statut = -2 THEN 1 ELSE 0 END) AS cancelled')
                ->innerJoin('r.patient', 'rp')
                ->andWhere('rp.deletedAt IS NULL');

            if ($from) $qb->andWhere('r.dateRdv >= :from')->setParameter('from', $from);
            if ($to)   $qb->andWhere('r.dateRdv <= :to')->setParameter('to', $to);

            $result = $qb->getQuery()->getSingleResult();

            $total = (int)$result['total'];

            return [
                'scheduled' => $total,
                'pending' => (int) ($result['pending'] ?? 0),
                'confirmed' => (int)$result['confirmed'],
                'cancelled' => (int)$result['cancelled'],
                'confirmationRate' => $total > 0 ? round(((int)$result['confirmed'] / $total) * 100) : 0,
            ];
        });
    }

    public function lowStockConsumables(): array
    {
        return $this->remember('report.lowStockConsumables', 180, function () {
            $items = $this->consommableRepo->createQueryBuilder('c')
                ->where('c.quantity < c.lowValue')
                ->getQuery()
                ->getResult();

            return array_map(fn(Consommable $item) => [
                'item' => $item->getNom(),
                'remaining' => $item->getQuantity(),
            ], $items);
        });
    }

    // ====================== POINT D'ENTRÉE PRINCIPAL ======================

    public function getReportsData(?string $period, ?string $customStart, ?string $customEnd, ?string $employeeId): array
    {
        $cacheKey = sprintf('report.data.%s.%s.%s.%s', $period ?? 'month', $customStart ?? 'none', $customEnd ?? 'none', $employeeId ?? 'all');

        return $this->remember($cacheKey, 180, function () use ($period, $customStart, $customEnd, $employeeId) {
            [$start, $end] = $this->resolveReportRange($period, $customStart, $customEnd);

            $doctorReports = $this->periodicDoctorReports($start, $end);
            $globalStats = $this->globalStats($start->format('Y-m-d'), $end->format('Y-m-d'));
            $consultStats = $this->periodicConsultations($start, $end);
            $appointmentStats = $this->periodicAppointments($start, $end);

            $employees = array_map(fn($d) => [
                'id' => $d['id'],
                'name' => $d['name'],
                'role' => 'Medecin',
                'consultations' => $d['consultations'],
                'patients' => $d['new_patients'] + $d['returning_patients'],
                'avgTime' => 0,
                'revenue' => round($d['revenue']),
            ], $doctorReports['doctors']);

            if ($employeeId) {
                $employees = array_values(array_filter($employees, fn($e) => (string)$e['id'] === (string)$employeeId));
            }

            return [
                'employees' => $employees,
                'roles' => $this->employeesDistribution(),
                'finances' => [
                    'revenue' => round((float)($globalStats['revenueTotal'] ?? 0)),
                    'expenses' => round((float)($doctorReports['kpi']['totalSalaries'] ?? 0)),
                    'net' => round((float)($globalStats['revenueTotal'] ?? 0) - (float)($doctorReports['kpi']['totalSalaries'] ?? 0)),
                    'unpaidCount' => count(array_filter(array_column($doctorReports['doctors'], 'reliquat'), fn($v) => $v > 0)),
                    'unpaidAmount' => round(array_sum(array_column($doctorReports['doctors'], 'reliquat'))),
                    'capitalTotal' => round((float)($globalStats['capitalTotal'] ?? 0)),
                ],
                'revenueTrend' => $this->buildRevenueTrend($start, $end),
                'patients' => $this->buildPatientsReport(),
                'appointmentsTrend' => $this->buildAppointmentsTrend($start, $end),
                'attendanceRate' => $appointmentStats['confirmationRate'] ?? 0,
                'noShows' => $appointmentStats['cancelled'] ?? 0,
            ];
        });
    }
}
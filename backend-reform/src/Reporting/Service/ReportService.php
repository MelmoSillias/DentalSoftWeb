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
     * Charge les consultations d'une période avec toutes les relations nécessaires au calcul.
     * Non mis en cache : les entités évoluent (clôture, actes, paiements) et une sérialisation
     * Doctrine figerait statut/collections, excluant les actes du total.
     *
     * @return Consultation[]
     */
    private function loadConsultationsForPeriod(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->consultRepo->createQueryBuilder('c')
            ->select('c', 'p', 'm', 'f', 'fp', 'fa', 'a', 'pay')
            ->distinct()
            ->leftJoin('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->leftJoin('c.facture', 'f')
            ->leftJoin('f.paiements', 'fp')
            ->leftJoin('c.factureAssurance', 'fa')
            ->leftJoin('c.actes', 'a')
            ->leftJoin('c.paiement', 'pay')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
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
        $consultations = $this->loadConsultationsForPeriod($from, $to);
        $doctors = $this->employeRepo->FindAllMedecin() ?? [];

        $consultationsByDoctor = [];
        foreach ($consultations as $consultation) {
            $doctorId = $consultation->getMedecin()?->getId();
            if ($doctorId === null) {
                continue;
            }
            $consultationsByDoctor[$doctorId][] = $consultation;
        }

        $doctorStats = [];
        $totalRevenue = $totalSalaries = 0.0;

        foreach ($doctors as $doctor) {
            $consults = $consultationsByDoctor[$doctor->getId()] ?? [];
            $stats = $this->computeDoctorStats($doctor, $consults);

            $totalRevenue += $stats['revenue'];
            $totalSalaries += $stats['salary'];

            $doctorStats[] = $stats;
        }

        return [
            'kpi' => [
                'totalRevenue' => $totalRevenue,
                'afterFees' => $totalRevenue - $totalSalaries,
                'totalSalaries' => $totalSalaries,
                'totalConsultations' => count($consultations),
            ],
            'doctors' => $doctorStats,
        ];
    }

    /**
     * @return array{amount: float, labels: string[]}
     */
    private function computeConsultationActs(Consultation $consultation, bool $includeActs): array
    {
        if (!$includeActs) {
            return ['amount' => 0.0, 'labels' => []];
        }

        $amount = 0.0;
        $labels = [];

        foreach ($consultation->getActes() as $acte) {
            $amount += (float) (($acte->getPrix() ?? 0) * max(1, (int) ($acte->getQuantite() ?? 1)));

            $label = trim((string) ($acte->getType() ?? ''));
            if ($label === '') {
                $label = trim((string) ($acte->getDescription() ?? ''));
            }
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return ['amount' => $amount, 'labels' => $labels];
    }

    /**
     * @return array{
     *     consultationAmount: float,
     *     actsAmount: float,
     *     totalAmount: float,
     *     patientPaid: float,
     *     reliquat: float,
     *     isPaidConsultation: bool,
     *     actLabels: string[]
     * }
     */
    private function resolveConsultationBilling(Consultation $consultation): array
    {
        $isClosed = $consultation->getStatut() === 1;
        $acts = $this->computeConsultationActs($consultation, $isClosed);
        $actsAmount = $acts['amount'];
        $actLabels = $acts['labels'];

        $factureAssurance = $consultation->getFactureAssurance();
        if ($factureAssurance !== null) {
            $consultationAmount = $factureAssurance->isConsultationPayante()
                ? (float) $factureAssurance->getConsultationAmount()
                : 0.0;
            $totalAmount = $consultationAmount + $actsAmount;
            $patientPaid = $totalAmount;

            return [
                'consultationAmount' => $consultationAmount,
                'actsAmount' => $actsAmount,
                'totalAmount' => $totalAmount,
                'patientPaid' => $patientPaid,
                'reliquat' => max(0.0, $totalAmount - $patientPaid),
                'isPaidConsultation' => $consultationAmount > 0.0,
                'actLabels' => $actLabels,
            ];
        }

        $consultationAmount = (float) ($consultation->getPaiement()?->getMontant() ?? 0);
        $totalAmount = $consultationAmount + $actsAmount;
        $patientPaid = $consultationAmount;

        $facture = $consultation->getFacture();
        if ($facture !== null) {
            $patientPaid += (float) $facture->computePatientPaidAmount();
        }

        return [
            'consultationAmount' => $consultationAmount,
            'actsAmount' => $actsAmount,
            'totalAmount' => $totalAmount,
            'patientPaid' => $patientPaid,
            'reliquat' => max(0.0, $totalAmount - $patientPaid),
            'isPaidConsultation' => $consultationAmount > 0.0,
            'actLabels' => $actLabels,
        ];
    }

    private function buildConsultationActLineDescription(bool $hasConsultationFee, array $actLabels): string
    {
        $parts = $hasConsultationFee ? ['Consultation'] : [];
        foreach ($actLabels as $label) {
            $parts[] = $label;
        }

        return implode(' + ', $parts) ?: 'Consultation';
    }

    private function computeDoctorSalary(Employe $doctor, float $revenue): float
    {
        return match ($doctor->getTypeSalaire()) {
            'pourcentage', 'percentage' => ($doctor->getValeurSalaire() ?? 0) / 100 * $revenue,
            'non_defini' => 0.0,
            default => (float) ($doctor->getValeurSalaire() ?? 0),
        };
    }

    private function computeDoctorStats(Employe $doctor, array $consultations): array
    {
        $paidConsultations = 0;
        $apport = $revenue = $reliquat = 0.0;
        $newPatients = $returningPatients = 0;
        $seenPatientIds = [];
        $actesList = [];

        foreach ($consultations as $consultation) {
            $billing = $this->resolveConsultationBilling($consultation);

            if ($billing['isPaidConsultation']) {
                $paidConsultations++;
            }

            $apport += $billing['totalAmount'];
            $revenue += $billing['patientPaid'];
            $reliquat += $billing['reliquat'];

            $patientId = $consultation->getPatient()?->getId();
            if ($patientId !== null) {
                if (!isset($seenPatientIds[$patientId])) {
                    $seenPatientIds[$patientId] = true;
                    $newPatients++;
                } else {
                    $returningPatients++;
                }
            }

            $actesList[] = [
                'date' => $consultation->getCreatedAt()?->format('d/m/Y'),
                'patient' => $consultation->getPatient()?->getFullName() ?? 'Inconnu',
                'description' => $this->buildConsultationActLineDescription(
                    $billing['consultationAmount'] > 0.0,
                    $billing['actLabels']
                ),
                'montant' => $billing['totalAmount'],
            ];
        }

        return [
            'id' => $doctor->getId(),
            'name' => $doctor->getFullName(),
            'consultations' => count($consultations),
            'new_patients' => $newPatients,
            'returning_patients' => $returningPatients,
            'revenue' => $revenue,
            'apport' => $apport,
            'reliquat' => $reliquat,
            'consultations_paid' => $paidConsultations,
            'salary' => $this->computeDoctorSalary($doctor, $revenue),
            'actes' => $actesList,
        ];
    }

    // ====================== AUTRES MÉTHODES (exemples optimisés) ======================

    /**
     * @return array<string, int> label => count (quantité)
     */
    public function periodicActsStats(?DateTime $from, ?DateTime $to): array
    {
        $cacheKey = 'report.periodicActsStats.' . ($from?->format('Ymd') ?? '') . '.' . ($to?->format('Ymd') ?? '');

        return $this->remember($cacheKey, 180, function () use ($from, $to) {
            $qb = $this->acteRepo->createQueryBuilder('a')
                ->select('a.type', 'a.description', 'SUM(COALESCE(a.quantite, 1)) AS total')
                ->innerJoin('a.consultation', 'c')
                ->innerJoin('c.patient', 'p')
                ->andWhere('p.deletedAt IS NULL');

            if ($from) {
                $qb->andWhere('c.CreatedAt >= :from')->setParameter('from', $from);
            }
            if ($to) {
                $qb->andWhere('c.CreatedAt <= :to')->setParameter('to', $to);
            }

            $rows = $qb
                ->groupBy('a.type', 'a.description')
                ->orderBy('total', 'DESC')
                ->getQuery()
                ->getArrayResult();

            $stats = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['type'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($row['description'] ?? ''));
                }
                if ($label === '') {
                    $label = 'Autre';
                }

                $stats[$label] = ($stats[$label] ?? 0) + (int) ($row['total'] ?? 0);
            }

            arsort($stats);

            return $stats;
        });
    }

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
<?php

namespace App\Reporting\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\TransactionRepository;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ActeMedicalRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\IdentityAccess\Entity\Employe;
use App\Inventory\Entity\Consommable;
use App\Inventory\Repository\ConsommableRepository;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\Scheduling\Repository\SalleRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        private DevisRepository $devisRepo,
        private CacheInterface $cache,
    ) {
    }

    private function remember(string $key, int $ttl, callable $builder): array
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($ttl, $builder) {
            $item->expiresAfter($ttl);
            return $builder();
        });
    }

    private function resolveReportRange(?string $period, ?string $customStart, ?string $customEnd): array
    {
        $now = new \DateTime();

        if ($period === 'custom' && $customStart && $customEnd) {
            return [
                new \DateTime($customStart),
                (new \DateTime($customEnd))->setTime(23, 59, 59),
            ];
        }

        return match ($period) {
            'today' => [
                (clone $now)->setTime(0, 0, 0),
                (clone $now)->setTime(23, 59, 59),
            ],
            'week' => [
                (clone $now)->modify('-6 days')->setTime(0, 0, 0),
                (clone $now)->setTime(23, 59, 59),
            ],
            'year' => [
                (clone $now)->modify('-11 months')->setTime(0, 0, 0),
                (clone $now)->setTime(23, 59, 59),
            ],
            default => [
                (clone $now)->modify('-29 days')->setTime(0, 0, 0),
                (clone $now)->setTime(23, 59, 59),
            ],
        };
    }

    private function buildPatientsReport(): array
    {
        $patients = $this->patientRepo->findAll();

        $male = 0;
        $female = 0;
        $ageGroups = [
            '<18' => 0,
            '18-30' => 0,
            '31-50' => 0,
            '51+' => 0,
        ];
        $regions = [];
        $today = new \DateTimeImmutable();

        foreach ($patients as $patient) {
            if ($patient->getSexe() === 'Homme') {
                $male++;
            } elseif ($patient->getSexe() === 'Femme') {
                $female++;
            }

            $birthDate = $patient->getDateNaissance();
            if ($birthDate instanceof \DateTimeInterface) {
                $age = $today->diff(\DateTimeImmutable::createFromInterface($birthDate))->y;
                if ($age < 18) {
                    $ageGroups['<18']++;
                } elseif ($age <= 30) {
                    $ageGroups['18-30']++;
                } elseif ($age <= 50) {
                    $ageGroups['31-50']++;
                } else {
                    $ageGroups['51+']++;
                }
            }

            $address = trim((string) $patient->getAdresse());
            if ($address === '') {
                continue;
            }

            $chunks = preg_split('/\s*,\s*/', $address);
            $region = trim((string) end($chunks));
            if ($region === '') {
                continue;
            }

            $regions[$region] = ($regions[$region] ?? 0) + 1;
        }

        arsort($regions);

        return [
            'male' => $male,
            'female' => $female,
            'ageGroups' => $ageGroups,
            'regions' => array_map(
                static fn(string $region, int $count): array => ['region' => $region, 'count' => $count],
                array_keys($regions),
                array_values($regions)
            ),
        ];
    }

    private function buildRevenueTrend(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->andWhere('t.dateTransaction >= :from')
            ->andWhere('t.dateTransaction <= :to')
            ->setParameter('from', $startDate)
            ->setParameter('to', $endDate)
            ->orderBy('t.dateTransaction', 'ASC')
            ->getQuery()
            ->getResult();

        $trend = [];

        foreach ($transactions as $transaction) {
            $date = $transaction->getDateTransaction()?->format('Y-m-d');
            if ($date === null) {
                continue;
            }

            $signedAmount = $transaction->getType() === 'Entrée'
                ? (float) $transaction->getMontant()
                : -1 * (float) $transaction->getMontant();

            $trend[$date] = ($trend[$date] ?? 0) + $signedAmount;
        }

        ksort($trend);

        return array_map(
            static fn(string $date, float $amount): array => ['date' => $date, 'amount' => round($amount)],
            array_keys($trend),
            array_values($trend)
        );
    }

    private function buildAppointmentsTrend(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $appointments = $this->rdvRepo->createQueryBuilder('r')
            ->andWhere('r.dateRdv >= :from')
            ->andWhere('r.dateRdv <= :to')
            ->setParameter('from', $startDate)
            ->setParameter('to', $endDate)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getResult();

        $consultations = $this->consultRepo->createQueryBuilder('c')
            ->andWhere('c.CreatedAt >= :from')
            ->andWhere('c.CreatedAt <= :to')
            ->setParameter('from', $startDate)
            ->setParameter('to', $endDate)
            ->orderBy('c.CreatedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $timeline = [];

        foreach ($appointments as $appointment) {
            $date = $appointment->getDateRdv()?->format('Y-m-d');
            if ($date === null) {
                continue;
            }

            $timeline[$date] ??= ['appointments' => 0, 'consultations' => 0];
            $timeline[$date]['appointments']++;
        }

        foreach ($consultations as $consultation) {
            $date = $consultation->getCreatedAt()?->format('Y-m-d');
            if ($date === null) {
                continue;
            }

            $timeline[$date] ??= ['appointments' => 0, 'consultations' => 0];
            $timeline[$date]['consultations']++;
        }

        ksort($timeline);

        return array_map(
            static fn(string $date, array $values): array => [
                'date' => $date,
                'appointments' => $values['appointments'],
                'consultations' => $values['consultations'],
            ],
            array_keys($timeline),
            array_values($timeline)
        );
    }

    public function globalStats(?string $from, ?string $to): array
    {
        $cacheKey = sprintf('report.globalStats.%s.%s', $from ?? 'none', $to ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($from, $to) {
        $fromDate = $from ? \DateTime::createFromFormat('Y-m-d', $from) : null;
        $toDate   = $to   ? \DateTime::createFromFormat('Y-m-d', $to)   : null;

        // 2. Transactions sur la période (TODO: filtrage dates si besoin)
        $transactions = $this->transactionRepo->findAll();

        $capitalBreakdown = [];
        $capitalTotal     = 0;
        $inCash           = 0;
        $revenueTotal     = 0;

        foreach ($transactions as $tx) {
            $mode   = $tx->getModeDePaiement()->getType();
            $amount = $tx->getMontant();

            $signed = ($tx->getType() === 'Entrée') ? $amount : -$amount;
            $capitalTotal += $signed;

            if (!isset($capitalBreakdown[$mode])) {
                $capitalBreakdown[$mode] = 0;
            }
            $capitalBreakdown[$mode] += $signed;

            if ($mode === 'Espèces') {
                $inCash += $signed;
            }

            if ($tx->getPaiementDevis() !== null) {
                $revenueTotal += $amount;
            }
        }

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

        return [
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
        });
    }

    public function employeesDistribution(): array
    {
        return $this->remember('report.employeesDistribution', 180, function () {
        $employeeRepository = $this->em->getRepository(Employe::class);

        $roles = $employeeRepository->createQueryBuilder('e')
            ->select('DISTINCT e.type')
            ->getQuery()
            ->getSingleColumnResult();

        $distribution = [];
        foreach ($roles as $role) {
            $count = $employeeRepository->count(['type' => $role]);
            $distribution[$role . 's'] = $count;
        }

        return $distribution;
        });
    }

    public function getReportsData(?string $period, ?string $customStart, ?string $customEnd, ?string $employeeId): array
    {
        $cacheKey = sprintf('report.data.%s.%s.%s.%s', $period ?? 'month', $customStart ?? 'none', $customEnd ?? 'none', $employeeId ?? 'all');
        return $this->remember($cacheKey, 180, function () use ($period, $customStart, $customEnd, $employeeId) {
        [$startDate, $endDate] = $this->resolveReportRange($period, $customStart, $customEnd);
        $from = $startDate->format('Y-m-d');
        $to = $endDate->format('Y-m-d');

        $doctorReports = $this->periodicDoctorReports(
            \DateTimeImmutable::createFromMutable($startDate),
            \DateTimeImmutable::createFromMutable($endDate)
        );
        $globalStats = $this->globalStats($from, $to);
        $consultationStats = $this->periodicConsultations($startDate, $endDate);
        $appointmentStats = $this->periodicAppointments($startDate, $endDate);

        $employees = array_map(static function (array $doctor): array {
            return [
                'id' => $doctor['id'],
                'name' => $doctor['name'],
                'role' => 'Medecin',
                'consultations' => $doctor['consultations'],
                'patients' => $doctor['new_patients'] + $doctor['returning_patients'],
                'avgTime' => 0,
                'revenue' => round((float) $doctor['revenue']),
            ];
        }, $doctorReports['doctors'] ?? []);

        if ($employeeId !== null && $employeeId !== '') {
            $employees = array_values(array_filter(
                $employees,
                static fn(array $employee): bool => (string) $employee['id'] === (string) $employeeId
            ));
        }

        $revenue = (float) ($doctorReports['kpi']['totalRevenue'] ?? $consultationStats['totalAmount'] ?? 0);
        $expenses = (float) ($doctorReports['kpi']['totalSalaries'] ?? 0);
        $unpaidAmounts = array_map(static fn(array $doctor): float => (float) ($doctor['reliquat'] ?? 0), $doctorReports['doctors'] ?? []);

        return [
            'employees' => $employees,
            'roles' => $this->employeesDistribution(),
            'finances' => [
                'revenue' => round($revenue),
                'expenses' => round($expenses),
                'net' => round($revenue - $expenses),
                'unpaidCount' => count(array_filter($unpaidAmounts, static fn(float $amount): bool => $amount > 0)),
                'unpaidAmount' => round(array_sum($unpaidAmounts)),
                'capitalTotal' => round((float) ($globalStats['capitalTotal'] ?? 0)),
            ],
            'revenueTrend' => $this->buildRevenueTrend($startDate, $endDate),
            'patients' => $this->buildPatientsReport(),
            'appointmentsTrend' => $this->buildAppointmentsTrend($startDate, $endDate),
            'attendanceRate' => $appointmentStats['confirmationRate'] ?? 0,
            'noShows' => $appointmentStats['cancelled'] ?? 0,
        ];
        });
    }

    public function lowStockConsumables(): array
    {
        return $this->remember('report.lowStockConsumables', 180, function () {
            $consommableRepository = $this->em->getRepository(Consommable::class);
            $lowStockItems = $consommableRepository->createQueryBuilder('c')
                ->where('c.quantity < c.lowValue')
                ->getQuery()
                ->getResult();

            return array_map(function ($item) {
                return [
                    'item'      => $item->getNom(),
                    'remaining' => $item->getQuantity()
                ];
            }, $lowStockItems);
        });
    }

    public function globalPatients(): array
    {
        return $this->remember('report.globalPatients', 180, function () {
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
                if ($p->getSexe() === 'Femme') {
                    $female++;
                } elseif ($p->getSexe() === 'Homme') {
                    $male++;
                }

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

            $averageAge = $countAge > 0 ? round($sumAge / $countAge, 1) : null;

            return [
                'total'      => $total,
                'female'     => $female,
                'male'       => $male,
                'minors'     => $minors,
                'adults'     => $adults,
                'seniors'    => $seniors,
                'averageAge' => $averageAge,
            ];
        });
    }

    public function periodicPatients(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicPatients.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
        $qb = $this->patientRepo->createQueryBuilder('p');
        if ($fromDate) {
            $qb->andWhere('p.dateInscription >= :from')->setParameter('from', $fromDate->format('Y-m-d'));
        }
        if ($toDate) {
            $qb->andWhere('p.dateInscription <= :to')->setParameter('to', $toDate->format('Y-m-d'));
        }
        $newCount = (int) $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

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

        return [
            'newPatients'       => (int) $newCount,
            'returningPatients' => (int) $returningCount,
        ];
        });
    }

    public function periodicConsultations(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicConsultations.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
        $qbTotal = $this->consultRepo->createQueryBuilder('c')->select('COUNT(c.id)');
        if ($fromDate) {
            $qbTotal->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbTotal->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $total = (int) $qbTotal->getQuery()->getSingleScalarResult();

        $qbPaid = $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.paiementDevis', 'pd');
        if ($fromDate) {
            $qbPaid->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbPaid->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $paid = (int) $qbPaid->getQuery()->getSingleScalarResult();
        $free = $total - $paid;

        $qbAmount = $this->transactionRepo->createQueryBuilder('t')
            ->select('SUM(t.montant) + SUM(CASE WHEN f.montant IS NOT NULL THEN f.montant ELSE 0 END)')
            ->leftJoin('t.paiementDevis', 'pd')
            ->leftJoin('pd.consultation', 'c')
            ->leftJoin('c.facture', 'f');

        if ($fromDate) {
            $qbAmount->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qbAmount->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }

        $totalAmount = (int) $qbAmount->getQuery()->getSingleScalarResult();

        $averageAmount = $total > 0 ? round($totalAmount / $total) : 0;

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

        return [
            'total'         => $total,
            'paid'          => $paid,
            'free'          => $free,
            'totalAmount'   => $totalAmount,
            'averageAmount' => $averageAmount,
            'topActs'       => $topActs,
        ];
        });
    }

    public function periodicAppointments(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicAppointments.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
        $qb = $this->rdvRepo->createQueryBuilder('r');
        if ($fromDate) {
            $qb->andWhere('r.dateRdv >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
        }
        if ($toDate) {
            $qb->andWhere('r.dateRdv <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
        }
        $appointments = $qb->getQuery()->getResult();

        $scheduled = count($appointments);
        $counts = [
            'pending'   => 0,
            'confirmed' => 0,
            'postponed' => 0,
            'cancelled' => 0,
        ];
        foreach ($appointments as $r) {
            switch ($r->getStatut()) {
                case 1:
                    $counts['confirmed']++; break;
                case 0:
                    $counts['pending']++; break;
                case -1:
                    $counts['postponed']++; break;
                case -2:
                    $counts['cancelled']++; break;
            }
        }

        $confirmationRate = $scheduled > 0 ? round($counts['confirmed'] / $scheduled * 100) : 0;
        $sumDelay = 0;
        foreach ($appointments as $r) {
            $created = $r->getDateCreation();
            $dateRdv = $r->getDateRdv();
            if ($created && $dateRdv) {
                $sumDelay += $created->diff($dateRdv)->days;
            }
        }
        $averageDelayDays = $scheduled > 0 ? round($sumDelay / $scheduled) : 0;

        return [
            'scheduled'        => $scheduled,
            'confirmed'        => $counts['confirmed'],
            'pending'          => $counts['pending'],
            'postponed'        => $counts['postponed'],
            'cancelled'        => $counts['cancelled'],
            'confirmationRate' => $confirmationRate,
            'averageDelayDays' => $averageDelayDays,
        ];
        });
    }

    public function periodicRoomUsage(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicRoomUsage.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
            $qb = $this->consultRepo->createQueryBuilder('c')
                ->select('s.nom AS room, COUNT(c.id) AS cnt')
                ->join('c.salle', 's');
            if ($fromDate) {
                $qb->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate->format('Y-m-d') . ' 00:00:00');
            }
            if ($toDate) {
                $qb->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate->format('Y-m-d') . ' 23:59:59');
            }
            $qb->groupBy('s.id');
            $rows = $qb->getQuery()->getArrayResult();

            $total = array_sum(array_map(fn($r) => (int)$r['cnt'], $rows));
            $usage = [];
            $topRoom  = null;
            $maxCount = 0;

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

            return [
                'usage'   => $usage,
                'topRoom' => $topRoom,
            ];
        });
    }

    public function periodicPaymentBalances(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicPaymentBalances.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
            $qb = $this->transactionRepo->createQueryBuilder('t')
                ->select(
                    'm.libelle AS mode',
                    "SUM(CASE WHEN t.type = :entry THEN t.montant ELSE -t.montant END) AS balance"
                )
                ->join('t.modeDePaiement', 'm')
                ->setParameter('entry', 'Entrée');

            if ($fromDate) {
                $qb->andWhere('t.dateTransaction >= :from')->setParameter('from', $fromDate);
            }
            if ($toDate) {
                $qb->andWhere('t.dateTransaction <= :to')->setParameter('to', $toDate);
            }

            $qb->groupBy('m.libelle');
            return $qb->getQuery()->getArrayResult();
        });
    }

    public function periodicPaymentFrequency(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicPaymentFrequency.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
            $qb = $this->transactionRepo->createQueryBuilder('t')
                ->select('m.libelle AS mode, COUNT(t.id) AS cnt')
                ->join('t.modeDePaiement', 'm');

            if ($fromDate) {
                $qb->andWhere('t.dateTransaction >= :from')->setParameter('from', $fromDate);
            }
            if ($toDate) {
                $qb->andWhere('t.dateTransaction <= :to')->setParameter('to', $toDate);
            }

            $qb->groupBy('m.libelle');
            $rows = $qb->getQuery()->getArrayResult();

            $totalCount = array_sum(array_map(fn($r) => (int)$r['cnt'], $rows));
            $frequency  = [];
            $topMode    = null;
            $maxCount   = 0;

            foreach ($rows as $r) {
                $count   = (int) $r['cnt'];
                $percent = $totalCount > 0 ? round($count * 100 / $totalCount) : 0;

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

            return [
                'frequency' => $frequency,
                'topMode'   => $topMode,
            ];
        });
    }

    public function periodicActsStats(?\DateTime $fromDate, ?\DateTime $toDate): array
    {
        $cacheKey = sprintf('report.periodicActsStats.%s.%s', $fromDate?->format('Ymd') ?? 'none', $toDate?->format('Ymd') ?? 'none');
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
            $knownTypes = [
                'Consultation', 'Détartrage', 'Extraction', 'Remplissage', 'Composite', 'Amalgame',
                'Traitement de canal', 'Traumatisme', 'Couronne', 'Blanchiment', 'Radio', 'Prothèse',
                'Orthodontie', 'Chirurgie',
            ];
            $acts = array_fill_keys($knownTypes, 0);

            $qb = $this->acteRepo->createQueryBuilder('a')
                ->select('a.type AS actType, COUNT(a.id) AS cnt')
                ->join('a.consultation', 'c');

            if ($fromDate) {
                $qb->andWhere('c.CreatedAt >= :from')->setParameter('from', $fromDate);
            }
            if ($toDate) {
                $qb->andWhere('c.CreatedAt <= :to')->setParameter('to', $toDate);
            }

            $qb->groupBy('a.type');
            $rows = $qb->getQuery()->getArrayResult();

            foreach ($rows as $r) {
                if (in_array($r['actType'], $knownTypes, true)) {
                    $acts[$r['actType']] = (int) $r['cnt'];
                }
            }

            return $acts;
        });
    }

    public function periodicDoctorReports(\DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): array
    {
        $cacheKey = sprintf('report.periodicDoctorReports.%s.%s', $fromDate->format('Ymd'), $toDate->format('Ymd'));
        return $this->remember($cacheKey, 180, function () use ($fromDate, $toDate) {
            $doctors = $this->em->getRepository(Employe::class)->findBy(['type' => 'medecin']);
            $allConsultations = $this->em->getRepository(Consultation::class)->findByDateRange($fromDate, $toDate);

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
                    if ($consult->getPaiementDevis()) {
                        $paid++;
                    } else {
                        $free++;
                    }

                $consultAmount = 0;
                if ($consult->getFacture() && $consult->getFacture()->getMontant()) {
                    $fact = $consult->getFacture();
                    $consultAmount = $fact->getMontant();
                    $apport += $consultAmount;
                    $totalAmount += $consultAmount;
                    $paiements[] = [
                        'date' => $fact->getDate()?->format('Y-m-d H:i'),
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

                $patientId = $consult->getPatient()->getId();
                if (!in_array($patientId, $patientIds)) {
                    $newPatients++;
                    $patientIds[] = $patientId;
                } else {
                    $returningPatients++;
                }

                $consultationDetails[] = [
                    'date' => $consult->getCreatedAt()->format('d/m/Y'),
                    'patient' => $consult->getPatient()->getFullName(),
                    'type' => $consult->getNoteSeance(),
                    'amount' => $consultAmount,
                    'paid' => $consult->getPaiementDevis() !== null
                ];

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

            $paiementsConsultations = $this->em->createQueryBuilder()
                ->select('pd')
                ->from('App\\Entity\\PaiementDevis', 'pd')
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

            $paiementsFactures = $this->em->createQueryBuilder()
                ->select('pd', 'f', 'c')
                ->from('App\\Entity\\PaiementDevis', 'pd')
                ->join('pd.devis', 'f')
                ->join('f.consultation', 'c')
                ->where('c.medecin = :doctor')
                ->andWhere('pd.date BETWEEN :from AND :to')
                ->setParameter('doctor', $doctor)
                ->setParameter('from', $fromDate)
                ->setParameter('to', $toDate)
                ->getQuery()
                ->getResult();

            foreach ($paiementsFactures as $pay) {
                $facture = $pay->getDevis();
                $consult = $facture->getConsultation();
                $patient = $consult->getPatient();
                $descriptions = [];
                foreach ($facture->getContenus() as $acte) {
                    $descriptions[] = $acte->getDesignation();
                }

                $paiementsPeriode[] = [
                    'date' => $pay->getDate()->format('Y-m-d H:i'),
                    'medecin' => $doctor->getFullName(),
                    'patient' => $patient?->getFullName() ?? 'Inconnu',
                    'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                    'description' => implode(', ', $descriptions),
                    'montant_total' => $facture->getMontant(),
                    'montant_paye' => $pay->getMontant(),
                    'reste' => $facture->getReste(),
                ];
                $revenue += $pay->getMontant();
            }

            usort($paiementsPeriode, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

            $salary = 0.0;
            $typeSalaire = $doctor->getTypeSalaire();
            $valeurSalaire = $doctor->getValeurSalaire() ?? 0.0;

            if ($typeSalaire === 'pourcentage') {
                $salary = ($valeurSalaire / 100) * $revenue;
            } elseif ($typeSalaire === 'non_defini') {
                $salary = 0.0;
            } else {
                $salary = $valeurSalaire;
            }

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

            return [
                'kpi' => [
                    'totalRevenue' => $totalRevenue,
                    'afterFees' => $totalRevenue - $totalSalaries,
                    'totalSalaries' => $totalSalaries,
                    'totalConsultations' => count($allConsultations),
                    'totalActs' => array_sum(array_column($doctorStats, 'acts')),
                ],
                'doctors' => $doctorStats,
            ];
        });
    }

    public function medecinDashboard(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('report.medecinDashboard.%d.%s.%s', $medecin->getId(), $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 180, function () use ($medecin, $from, $to) {
        $consultations = $this->em->getRepository(Consultation::class)->findBy(['medecin' => $medecin]);
        $patientsFromConsultations = array_map(fn($c) => $c->getPatient(), $consultations);
        $rdvs = $this->em->getRepository(Rdv::class)->findBy(['medecin' => $medecin]);
        $patientsFromRdvs = array_map(fn($r) => $r->getPatient(), $rdvs);
        $patients = array_unique(array_merge($patientsFromConsultations, $patientsFromRdvs), SORT_REGULAR);

        $consultationsPeriode = array_filter($consultations, fn($c) => $c->getCreatedAt() >= $from && $c->getCreatedAt() <= $to);

        $paidConsults = 0; $freeConsults = 0; $apport = 0.0; $revenue = 0.0;
        $actes = []; $paiements = [];

        foreach ($consultationsPeriode as $consult) {
            if ($consult->getFacture()) { $paidConsults++; } else { $freeConsults++; }

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
        $paiementsConsultations = $this->em->createQueryBuilder()
            ->select('pd')
            ->from('App\\Entity\\PaiementDevis', 'pd')
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

        $paiementsFactures = $this->em->createQueryBuilder()
            ->select('pd', 'f', 'c')
            ->from('App\\Entity\\PaiementDevis', 'pd')
            ->join('pd.devis', 'f')
            ->join('f.consultation', 'c')
            ->where('c.medecin = :doctor')
            ->andWhere('pd.date BETWEEN :from AND :to')
            ->setParameter('doctor', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        foreach ($paiementsFactures as $pay) {
            $facture = $pay->getDevis();
            $consult = $facture->getConsultation();
            $patient = $consult->getPatient();
            $descriptions = [];
            foreach ($facture->getContenus() as $acte) {
                $descriptions[] = $acte->getDesignation();
            }

            $paiementsPeriode[] = [
                'date' => $pay->getDate()->format('Y-m-d H:i'),
                'medecin' => $medecin->getFullName(),
                'patient' => $patient?->getFullName() ?? 'Inconnu',
                'telephone' => $patient?->getTelephone() ?? '-- -- -- --',
                'description' => implode(', ', $descriptions),
                'montant_total' => $facture->getMontant(),
                'montant_paye' => $pay->getMontant(),
                'reste' => $facture->getReste(),
            ];
            $revenue += $pay->getMontant();
        }

        usort($paiementsPeriode, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

        $today = new \DateTimeImmutable();
        $startToday = $today->setTime(0, 0, 0);
        $endToday = $today->setTime(23, 59, 59);

        $rdvToday = $this->em->getRepository(Rdv::class)->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.medecin = :medecin')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('medecin', $medecin)
            ->setParameter('start', $startToday)
            ->setParameter('end', $endToday)
            ->getQuery()
            ->getSingleScalarResult();

        $rdvQb = $this->em->getRepository(Rdv::class)->createQueryBuilder('r')
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
                case 0: $rdvStats['rdvEnAttente']++; break;
                case 1: $rdvStats['rdvValides']++; break;
                case -1: $rdvStats['rdvReportes']++; break;
                case -2: $rdvStats['rdvAnnules']++; break;
            }
        }

        return [
            'fullName' => $medecin->getFullName(),
            'identity' => [
                'nom' => $medecin->getNom(),
                'prenom' => $medecin->getPrenom(),
                'fullName' => $medecin->getFullName(),
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
            ],
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
                'actesMedicaux' => $actes,
                'revenue' => $revenue,
                'apport' => $apport,
                'paiements_period' => $paiementsPeriode,
            ] + $rdvStats
        ];
        });
    }

    public function receptionDashboard(\DateTime $dateStart, \DateTime $dateEnd): array
    {
        $cacheKey = sprintf('report.receptionDashboard.%s.%s', $dateStart->format('Ymd'), $dateEnd->format('Ymd'));
        return $this->remember($cacheKey, 180, function () use ($dateStart, $dateEnd) {
        $newPatients = $this->em->createQuery("\n        SELECT COUNT(p.id) FROM App\\Entity\\Patient p \n        WHERE p.dateInscription BETWEEN :start AND :end\n    ")
            ->setParameters(['start' => $dateStart, 'end' => $dateEnd])
            ->getSingleScalarResult();

        $totalConsultations = $this->em->createQuery("\n        SELECT COUNT(c.id) FROM App\\Entity\\Consultation c \n        WHERE c.CreatedAt BETWEEN :start AND :end\n    ")
            ->setParameters(['start' => $dateStart, 'end' => $dateEnd])
            ->getSingleScalarResult();

        $pendingConsultations = $this->em->createQuery("\n        SELECT COUNT(c.id) FROM App\\Entity\\Consultation c \n        WHERE c.CreatedAt BETWEEN :start AND :end AND c.statut = 0\n    ")
            ->setParameters(['start' => $dateStart, 'end' => $dateEnd])
            ->getSingleScalarResult();

        $rdvStats = $this->em->createQuery("\n        SELECT \n            COUNT(r.id) AS total,\n            SUM(CASE WHEN r.statut = 0 THEN 1 ELSE 0 END) AS pending,\n            SUM(CASE WHEN r.statut = 1 THEN 1 ELSE 0 END) AS confirmed,\n            SUM(CASE WHEN r.statut = 2 THEN 1 ELSE 0 END) AS cancelled,\n            SUM(CASE WHEN r.statut = 3 THEN 1 ELSE 0 END) AS postponed\n        FROM App\\Entity\\Rdv r\n        WHERE r.dateRdv BETWEEN :start AND :end\n    ")
            ->setParameters(['start' => $dateStart, 'end' => $dateEnd])
            ->getSingleResult();

        $modeEspeces = $this->em->getRepository(ModeDePaiement::class)->find(0);

        $revenusEspeces = $this->em->createQuery("\n        SELECT SUM(t.montant)\n        FROM App\\Entity\\Transaction t\n        WHERE t.dateTransaction BETWEEN :start AND :end\n        AND t.modeDePaiement = :mode\n        AND t.type = 'Entrée'\n    ")
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->setParameter('mode', $modeEspeces)
            ->getSingleScalarResult();

        $revenusTotaux = $this->em->createQuery("\n    SELECT SUM(t.montant)\n    FROM App\\Entity\\Transaction t\n    WHERE t.dateTransaction BETWEEN :start AND :end\n    AND t.type = 'Entrée'\n")
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->getSingleScalarResult();

        return [
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
        ];
        });
    }
}

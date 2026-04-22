<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Entity\Devis;
use App\Entity\Employe;
use App\Entity\PaiementDevis;
use App\Entity\Rdv;
use App\Entity\Transaction;
use App\Repository\ActeMedicalRepository;
use App\Repository\ConsultationRepository;
use App\Repository\DevisRepository;
use App\Repository\PatientRepository;
use App\Repository\RdvRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DashboardService
{
    public function __construct(
        private PatientRepository $patientRepo,
        private ConsultationRepository $consultRepo,
        private RdvRepository $rdvRepo,
        private DevisRepository $devisRepo,
        private TransactionRepository $transactionRepo,
        private ActeMedicalRepository $acteRepo,
        private ReportService $reportService,
        private EntityManagerInterface $em,
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

    public function getAdminCards(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.admin.cards.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
        $newPatients = (int) $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.dateInscription BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $totalPatients = (int) $this->patientRepo->count([]);

        $totalConsultations = (int) $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $paidConsultations = (int) $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.paiementDevis', 'pd')
            ->leftJoin('c.facture', 'f')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->andWhere('pd.id IS NOT NULL OR f.id IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingAppointments = (int) $this->rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.statut = 0')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledAppointments = (int) $this->rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.statut = -2')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $cashTotal = (float) $this->transactionRepo->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.montant), 0)')
            ->join('t.modeDePaiement', 'm')
            ->andWhere('t.type = :entry')
            ->andWhere('m.type = :cash')
            ->andWhere('t.dateTransaction BETWEEN :from AND :to')
            ->setParameter('entry', 'Entrée')
            ->setParameter('cash', 'Espèces')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $unpaidAmount = (float) $this->devisRepo->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.reste), 0)')
            ->andWhere('d.statut = 0')
            ->andWhere('d.type = 1')
            ->andWhere('d.date BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingConsultations = $this->consultRepo->createQueryBuilder('c')
            ->andWhere('c.statut = 0')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $waitStats = $this->computeWaitStats($pendingConsultations);

        return [
            'patients' => [
                'new' => $newPatients,
                'total' => $totalPatients,
            ],
            'consultations' => [
                'total' => $totalConsultations,
                'paid' => $paidConsultations,
            ],
            'appointments' => [
                'pending' => $pendingAppointments,
                'cancelled' => $cancelledAppointments,
            ],
            'cash' => [
                'total' => $cashTotal,
                'unpaid' => $unpaidAmount,
            ],
            'pendingConsultations' => [
                'total' => $waitStats['count'],
                'avgWaitMinutes' => $waitStats['avgMinutes'],
            ],
        ];
        });
    }

    public function getAdminCarousels(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.admin.carousels.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
        $doctorConsultations = $this->consultRepo->createQueryBuilder('c')
            ->select(
                "m.id AS id",
                "CONCAT(m.nom, ' ', m.prenom) AS name",
                'COUNT(c.id) AS total',
                'SUM(CASE WHEN pd.id IS NOT NULL OR f.id IS NOT NULL THEN 1 ELSE 0 END) AS paid'
            )
            ->join('c.medecin', 'm')
            ->leftJoin('c.paiementDevis', 'pd')
            ->leftJoin('c.facture', 'f')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('m.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $doctorConsultations = array_map(function (array $row) {
            $total = (int) ($row['total'] ?? 0);
            $paid = (int) ($row['paid'] ?? 0);
            $rate = $total > 0 ? round(($paid / $total) * 100) : 0;
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'total' => $total,
                'paid' => $paid,
                'paidRate' => $rate,
            ];
        }, $doctorConsultations);

        $doctorActs = $this->acteRepo->createQueryBuilder('a')
            ->select(
                "m.id AS id",
                "CONCAT(m.nom, ' ', m.prenom) AS name",
                'COALESCE(SUM(a.quantite), 0) AS acts'
            )
            ->join('a.consultation', 'c')
            ->join('c.medecin', 'm')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('m.id')
            ->orderBy('acts', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $doctorActs = array_map(fn(array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'acts' => (int) $row['acts'],
        ], $doctorActs);

        $financeEntries = $this->aggregateEntriesByModeAndWeekday($from, $to);
        $financeOut = $this->aggregateDailyTransactions($from, $to);
        $capitalEvolution = $this->aggregateCapitalEvolution($financeOut['daily']);

        return [
            'doctorConsultations' => $doctorConsultations,
            'doctorActs' => $doctorActs,
            'financeEntries' => $financeEntries,
            'financeOut' => $financeOut,
            'capitalEvolution' => $capitalEvolution,
        ];
        });
    }

    public function getAdminTabs(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.admin.tabs.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            return [
                'appointments' => $this->listAppointments($from, $to),
                'pendingConsultations' => $this->listPendingConsultations($from, $to),
                'unpaidInvoices' => $this->listUnpaidInvoices($from, $to),
            ];
        });
    }

    public function getMedecinCards(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.medecin.%d.cards.%s.%s', $medecin->getId(), $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($medecin, $from, $to) {
        $newPatients = (int) $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.consultations', 'c')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('p.dateInscription BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $totalPatientsRows = $this->patientRepo->createQueryBuilder('p')
            ->select('DISTINCT p.id')
            ->innerJoin('p.consultations', 'c')
            ->andWhere('c.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getArrayResult();

        $totalPatients = count($totalPatientsRows);

        $pendingConsultations = $this->consultRepo->createQueryBuilder('c')
            ->andWhere('c.statut = 0')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $waitStats = $this->computeWaitStats($pendingConsultations);

        $pendingAppointments = (int) $this->rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.statut = 0')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledAppointments = (int) $this->rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.statut = -2')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $totalConsultations = (int) $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $paidConsultations = (int) $this->consultRepo->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.paiementDevis', 'pd')
            ->leftJoin('c.facture', 'f')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->andWhere('pd.id IS NOT NULL OR f.id IS NOT NULL')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $amounts = $this->devisRepo->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.montant), 0) AS total, COALESCE(SUM(d.reste), 0) AS unpaid')
            ->join('d.consultation', 'c')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('d.type = 1')
            ->andWhere('d.date BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return [
            'patients' => [
                'new' => $newPatients,
                'total' => $totalPatients,
            ],
            'pendingConsultations' => [
                'total' => $waitStats['count'],
                'avgWaitMinutes' => $waitStats['avgMinutes'],
            ],
            'appointments' => [
                'pending' => $pendingAppointments,
                'cancelled' => $cancelledAppointments,
            ],
            'consultations' => [
                'total' => $totalConsultations,
                'paid' => $paidConsultations,
            ],
            'revenue' => [
                'total' => (float) $amounts['total'],
                'unpaid' => (float) $amounts['unpaid'],
            ],
        ];
    }); }

    public function getMedecinCarousels(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.medecin.%d.carousels.%s.%s', $medecin->getId(), $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($medecin, $from, $to) {
        $consultations = $this->consultRepo->createQueryBuilder('c')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $consultationsByWeekday = $this->aggregateByWeekday($consultations, fn(Consultation $c) => $c->getCreatedAt());

        $paiements = $this->fetchPaiementsForMedecin($medecin, $from, $to);
        $revenuesByWeekday = $this->aggregateByWeekday($paiements, fn(PaiementDevis $p) => $p->getDate(), fn(PaiementDevis $p) => $p->getMontant());

        return [
            'consultationsByWeekday' => $consultationsByWeekday,
            'revenuesByWeekday' => $revenuesByWeekday,
        ];
        });
    }

    public function getMedecinTabs(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.medecin.%d.tabs.%s.%s', $medecin->getId(), $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($medecin, $from, $to) {
            return [
                'appointments' => $this->listAppointments($from, $to, $medecin),
                'pendingConsultations' => $this->listPendingConsultations($from, $to, $medecin),
                'invoices' => $this->listUnpaidInvoices($from, $to, $medecin),
                'topActs' => $this->listTopActs($from, $to, $medecin),
            ];
        });
    }

    public function getReceptionCards(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.reception.cards.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $cards = $this->getAdminCards($from, $to);

            return [
                'patients' => $cards['patients'],
                'consultations' => $cards['consultations'],
                'appointments' => $cards['appointments'],
                'cash' => $cards['cash'],
            ];
        });
    }

    public function getReceptionCarousels(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.reception.carousels.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $stats = $this->reportService->periodicDoctorReports($from, $to);

            return [
                'doctorReports' => [
                    'period' => [
                        'from' => $from->format('d/m/Y'),
                        'to' => $to->format('d/m/Y'),
                    ],
                    'kpi' => $stats['kpi'],
                    'doctors' => $stats['doctors'],
                ],
            ];
        });
    }

    public function getReceptionTabs(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.reception.tabs.%s.%s', $from->format('Ymd'), $to->format('Ymd'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            return [
                'appointments' => $this->listAppointments($from, $to),
                'pendingConsultations' => $this->listPendingConsultations($from, $to),
                'unpaidInvoices' => $this->listUnpaidInvoices($from, $to),
                'payments' => $this->listPayments($from, $to),
            ];
        });
    }

    private function listAppointments(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $medecin = null): array
    {
        $qb = $this->rdvRepo->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')->addSelect('p')
            ->leftJoin('r.medecin', 'm')->addSelect('m')
            ->andWhere('r.statut = 0')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.dateRdv', 'ASC')
            ->setMaxResults(10);

        if ($medecin) {
            $qb->andWhere('r.medecin = :medecin')->setParameter('medecin', $medecin);
        }

        $rdvs = $qb->getQuery()->getResult();

        return array_map(fn(Rdv $rdv) => [
            'id' => $rdv->getId(),
            'patient' => $rdv->getPatient()?->getFullName() ?? 'Inconnu',
            'medecin' => $rdv->getMedecin()?->getFullName() ?? 'Inconnu',
            'date' => $this->formatDate($rdv->getDateRdv()),
            'motif' => $rdv->getDescription() ?? '',
        ], $rdvs);
    }

    private function listPendingConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $medecin = null): array
    {
        $qb = $this->consultRepo->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.medecin', 'm')->addSelect('m')
            ->andWhere('c.statut = 0')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.CreatedAt', 'ASC')
            ->setMaxResults(10);

        if ($medecin) {
            $qb->andWhere('c.medecin = :medecin')->setParameter('medecin', $medecin);
        }

        $now = new \DateTimeImmutable();
        $consultations = $qb->getQuery()->getResult();

        return array_map(function (Consultation $consultation) use ($now) {
            $createdAt = $consultation->getCreatedAt();
            $waitMinutes = $this->minutesDiff($createdAt, $now);

            return [
                'id' => $consultation->getId(),
                'patient' => $consultation->getPatient()?->getFullName() ?? 'Inconnu',
                'medecin' => $consultation->getMedecin()?->getFullName() ?? 'Inconnu',
                'date' => $this->formatDate($consultation->getCreatedAt()),
                'waitingTime' => $this->formatWaitMinutes($waitMinutes),
            ];
        }, $consultations);
    }

    private function listUnpaidInvoices(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $medecin = null): array
    {
        $qb = $this->devisRepo->createQueryBuilder('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->leftJoin('d.consultation', 'c')->addSelect('c')
            ->leftJoin('c.medecin', 'm')->addSelect('m')
            ->andWhere('d.type = 1')
            ->andWhere('d.statut = 0')
            ->andWhere('d.date BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('d.date', 'DESC')
            ->setMaxResults(10);

        if ($medecin) {
            $qb->andWhere('c.medecin = :medecin')->setParameter('medecin', $medecin);
        }

        $invoices = $qb->getQuery()->getResult();

        return array_map(function (Devis $devis) {
            $patient = $devis->getFicheMedicale()?->getPatient() ?? $devis->getFiche()?->getPatient();

            return [
                'id' => $devis->getId(),
                'patient' => $patient?->getFullName() ?? 'Inconnu',
                'amount' => (float) $devis->getMontant(),
                'date' => $this->formatDate($devis->getDate()),
            ];
        }, $invoices);
    }

    private function listPayments(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('pd', 'c', 'd', 'f', 'p')
            ->from(PaiementDevis::class, 'pd')
            ->leftJoin('pd.consultation', 'c')
            ->leftJoin('pd.devis', 'd')
            ->leftJoin('d.fiche', 'f')
            ->leftJoin('f.patient', 'p')
            ->leftJoin('d.ficheMedicale', 'fm')
            ->leftJoin('fm.patient', 'pm')
            ->andWhere('pd.date BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('pd.date', 'DESC')
            ->setMaxResults(10);

        $paiements = $qb->getQuery()->getResult();

        return array_map(function (PaiementDevis $paiement) {
            $patient = $paiement->getConsultation()?->getPatient()
                ?? $paiement->getDevis()?->getFicheMedicale()?->getPatient()
                ?? $paiement->getDevis()?->getFiche()?->getPatient();

            return [
                'id' => $paiement->getId(),
                'patient' => $patient?->getFullName() ?? 'Inconnu',
                'amount' => (float) $paiement->getMontant(),
                'date' => $this->formatDate($paiement->getDate()),
            ];
        }, $paiements);
    }

    private function listTopActs(\DateTimeImmutable $from, \DateTimeImmutable $to, Employe $medecin): array
    {
        $rows = $this->acteRepo->createQueryBuilder('a')
            ->select('a.type AS label, COALESCE(SUM(a.quantite), 0) AS total')
            ->join('a.consultation', 'c')
            ->andWhere('c.medecin = :medecin')
            ->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('medecin', $medecin)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('a.type')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn(array $row) => [
            'label' => $row['label'],
            'total' => (int) $row['total'],
        ], $rows);
    }

    private function computeWaitStats(array $consultations): array
    {
        $now = new \DateTimeImmutable();
        $count = 0;
        $sumMinutes = 0;

        foreach ($consultations as $consultation) {
            if (!$consultation instanceof Consultation) {
                continue;
            }
            $createdAt = $consultation->getCreatedAt();
            if (!$createdAt) {
                continue;
            }
            $sumMinutes += $this->minutesDiff($createdAt, $now);
            $count++;
        }

        $avgMinutes = $count > 0 ? (int) round($sumMinutes / $count) : 0;

        return [
            'count' => $count,
            'avgMinutes' => $avgMinutes,
        ];
    }

    private function minutesDiff(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        if (!$from || !$to) {
            return 0;
        }
        $seconds = max(0, $to->getTimestamp() - $from->getTimestamp());
        return (int) round($seconds / 60);
    }

    private function formatWaitMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '--';
        }
        return $minutes . ' min';
    }

    private function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i');
    }

    private function aggregateEntriesByModeAndWeekday(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->andWhere('t.type = :entry')
            ->andWhere('t.dateTransaction BETWEEN :from AND :to')
            ->setParameter('entry', 'Entrée')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $byMode = [];
        $byWeekday = array_fill(1, 7, 0.0);

        foreach ($transactions as $tx) {
            if (!$tx instanceof Transaction) {
                continue;
            }
            $amount = (float) $tx->getMontant();
            $mode = $tx->getModeDePaiement()?->getLibelle() ?? 'Inconnu';
            $weekday = (int) $tx->getDateTransaction()?->format('N');

            $byMode[$mode] = ($byMode[$mode] ?? 0) + $amount;
            if ($weekday >= 1 && $weekday <= 7) {
                $byWeekday[$weekday] += $amount;
            }
        }

        $modeRows = [];
        foreach ($byMode as $label => $total) {
            $modeRows[] = ['label' => $label, 'total' => $total];
        }

        $weekdayRows = [];
        foreach ($byWeekday as $weekday => $total) {
            $weekdayRows[] = [
                'label' => $this->weekdayLabel($weekday),
                'total' => $total,
            ];
        }

        return [
            'byMode' => $modeRows,
            'byWeekday' => $weekdayRows,
        ];
    }

    private function aggregateDailyTransactions(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->andWhere('t.dateTransaction BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $daily = [];
        foreach ($transactions as $tx) {
            if (!$tx instanceof Transaction) {
                continue;
            }
            $date = $tx->getDateTransaction()?->format('Y-m-d');
            if (!$date) {
                continue;
            }
            if (!isset($daily[$date])) {
                $daily[$date] = ['entries' => 0.0, 'exits' => 0.0];
            }
            $amount = (float) $tx->getMontant();
            if ($tx->getType() === 'Entrée') {
                $daily[$date]['entries'] += $amount;
            } else {
                $daily[$date]['exits'] += $amount;
            }
        }

        ksort($daily);

        $rows = [];
        foreach ($daily as $date => $values) {
            $rows[] = [
                'date' => $date,
                'entries' => $values['entries'],
                'exits' => $values['exits'],
                'net' => $values['entries'] - $values['exits'],
            ];
        }

        return [
            'daily' => $rows,
        ];
    }

    private function aggregateCapitalEvolution(array $dailyRows): array
    {
        $balance = 0.0;
        $rows = [];
        foreach ($dailyRows as $row) {
            $balance += (float) ($row['net'] ?? 0);
            $rows[] = [
                'date' => $row['date'],
                'balance' => $balance,
            ];
        }

        return [
            'daily' => $rows,
        ];
    }

    private function aggregateByWeekday(array $items, callable $dateResolver, ?callable $amountResolver = null): array
    {
        $totals = array_fill(1, 7, 0.0);

        foreach ($items as $item) {
            $date = $dateResolver($item);
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }
            $weekday = (int) $date->format('N');
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $amount = $amountResolver ? (float) $amountResolver($item) : 1.0;
            $totals[$weekday] += $amount;
        }

        $rows = [];
        foreach ($totals as $weekday => $total) {
            $rows[] = [
                'label' => $this->weekdayLabel($weekday),
                'total' => $total,
            ];
        }

        return $rows;
    }

    private function fetchPaiementsForMedecin(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('pd', 'c', 'd', 'dc')
            ->from(PaiementDevis::class, 'pd')
            ->leftJoin('pd.consultation', 'c')
            ->leftJoin('pd.devis', 'd')
            ->leftJoin('d.consultation', 'dc')
            ->andWhere('pd.date BETWEEN :from AND :to')
            ->andWhere('(c.medecin = :medecin OR dc.medecin = :medecin)')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getResult();
    }
    

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
            default => '--',
        };
    }
}

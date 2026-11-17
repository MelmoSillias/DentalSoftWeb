<?php

declare(strict_types=1);

namespace App\Focus\Application\Service;

use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\CareDelivery\Domain\Entity\Consultation;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Scheduling\Domain\Entity\Rdv;
use App\Focus\Application\Port\DashboardQueriesInterface;
use App\Focus\Application\Port\DashboardReportingInterface;
use App\Focus\Application\Port\DashboardCacheInterface;
use App\Focus\Application\Port\DashboardClockInterface;

class DashboardService
{
    public function __construct(
        private DashboardQueriesInterface $queries,
        private DashboardReportingInterface $reports,
        private DashboardCacheInterface $cache,
        private DashboardClockInterface $clock,
    ) {
    }

    private function remember(string $key, int $ttl, callable $builder): array
    {
        return $this->cache->remember($key, $ttl, $builder);
    }

    public function getAdminCards(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.admin.cards.v2.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $newPatients = $this->queries->countNewPatients($from, $to);
            $totalPatients = $this->queries->countPatients();
            $totalConsultations = $this->queries->countConsultations($from, $to);
            $paidConsultations = $this->countPaidConsultations($from, $to);
            $pendingAppointments = $this->queries->countAppointments($from, $to, 0);
            $cancelledAppointments = $this->queries->countAppointments($from, $to, -2);
            $cashTotal = $this->reports->cashRevenue($from, $to);
            $facturesForPeriod = $this->queries->invoicesForPeriod($from, $to);
            $unpaidAmount = array_reduce($facturesForPeriod, static fn(float $sum, Facture $facture): float => $sum + max(0.0, (float) $facture->getRestePatient()), 0.0);
            $pendingConsultations = $this->queries->pendingConsultations($from, $to);
            $waitStats = $this->computeWaitStats($pendingConsultations);
            return [
                'patients' => [
                    'new' => $newPatients,
                    'total' => $totalPatients,
                ],
                'consultations' => [
                    'total' => $totalConsultations,
                    'paid' => $paidConsultations,
                    'free' => max(0, $totalConsultations - $paidConsultations),
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
        $cacheKey = sprintf('dashboard.admin.carousels.v2.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $doctorConsultations = $this->queries->doctorConsultationTotals($from, $to);
            $doctorConsultations = array_map(function (array $row) {
                $total = (int) ($row['total'] ?? 0);
                $paid = (int) ($row['paid'] ?? 0);
                $rate = $total > 0 ? round($paid / $total * 100) : 0;
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'total' => $total,
                    'paid' => $paid,
                    'paidRate' => $rate,
                ];
            }, $doctorConsultations);
            $doctorActs = $this->queries->doctorActTotals($from, $to);
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
        $cacheKey = sprintf('dashboard.admin.tabs.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
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
        $cacheKey = sprintf('dashboard.medecin.%d.cards.v2.%s.%s', $medecin->getId(), $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($medecin, $from, $to) {
            $newPatients = $this->queries->countNewPatients($from, $to, $medecin);
            $totalPatients = $this->queries->countPatients($medecin);
            $pendingConsultations = $this->queries->pendingConsultations($from, $to, $medecin);
            $waitStats = $this->computeWaitStats($pendingConsultations);
            $pendingAppointments = $this->queries->countAppointments($from, $to, 0, $medecin);
            $cancelledAppointments = $this->queries->countAppointments($from, $to, -2, $medecin);
            $totalConsultations = $this->queries->countConsultations($from, $to, $medecin);
            $paidConsultations = $this->countPaidConsultations($from, $to, $medecin);
            $facturesByMedecin = $this->queries->invoicesForDoctor($medecin, $from, $to);
            $amounts = [
                'total' => array_reduce($facturesByMedecin, static fn(float $sum, Facture $facture): float => $sum + (float) $facture->getMontantTotal(), 0.0),
                'unpaid' => array_reduce($facturesByMedecin, static fn(float $sum, Facture $facture): float => $sum + max(0.0, (float) $facture->getRestePatient()), 0.0),
            ];
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
                    'free' => max(0, $totalConsultations - $paidConsultations),
                ],
                'revenue' => [
                    'total' => (float) $amounts['total'],
                    'unpaid' => (float) $amounts['unpaid'],
                ],
            ];
        });
    }

    public function getMedecinCarousels(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.medecin.%d.carousels.%s.%s', $medecin->getId(), $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($medecin, $from, $to) {
            $consultations = $this->queries->doctorConsultations($medecin, $from, $to);
            $consultationsByWeekday = $this->aggregateByWeekday($consultations, fn(Consultation $c) => $c->getCreatedAt());
            $paiements = $this->fetchPaiementsForMedecin($medecin, $from, $to);
            $revenuesByWeekday = $this->aggregateByWeekday($paiements, fn(Paiement $p) => $p->getDate(), fn(Paiement $p) => $p->getMontant());
            return [
                'consultationsByWeekday' => $consultationsByWeekday,
                'revenuesByWeekday' => $revenuesByWeekday,
            ];
        });
    }

    public function getMedecinTabs(Employe $medecin, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.medecin.%d.tabs.%s.%s', $medecin->getId(), $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
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
        $cacheKey = sprintf('dashboard.reception.cards.v2.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $cards = $this->getAdminCards($from, $to);
            return [
                'patients' => $cards['patients'],
                'consultations' => $cards['consultations'],
                'appointments' => $cards['appointments'],
                'cash' => $cards['cash'],
                'pendingConsultations' => $cards['pendingConsultations'],
            ];
        });
    }

    public function getReceptionCarousels(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $cacheKey = sprintf('dashboard.reception.carousels.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
        return $this->remember($cacheKey, 120, function () use ($from, $to) {
            $stats = $this->reports->doctorReports($from, $to);
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
        $cacheKey = sprintf('dashboard.reception.tabs.%s.%s', $from->format('YmdHis.uO'), $to->format('YmdHis.uO'));
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
        $rdvs = $this->queries->pendingAppointments($from, $to, $medecin);
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
        $now = $this->clock->now();
        $consultations = $this->queries->pendingConsultations($from, $to, $medecin, 10);
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
        $invoices = array_values(array_filter($this->queries->recentInvoices($from, $to, $medecin), static fn(Facture $facture): bool => $facture->getRestePatient() > 0.0));
        return array_map(function (Facture $facture) {
            $patient = $facture->getConsultation()?->getPatient();
            return [
                'id' => $facture->getId(),
                'patient' => $patient?->getFullName() ?? 'Inconnu',
                'amount' => (float) $facture->getMontantTotal(),
                'date' => $this->formatDate($facture->getDateFacture()),
            ];
        }, $invoices);
    }

    private function listPayments(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $paiements = $this->queries->recentPayments($from, $to);
        return array_map(function (Paiement $paiement) {
            $patient = $paiement->getConsultation()?->getPatient() ?? $paiement->getFacture()?->getConsultation()?->getPatient() ?? $paiement->getFactureAssurance()?->getPatient();
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
        $rows = $this->queries->topDoctorActs($medecin, $from, $to);
        return array_map(fn(array $row) => [
            'label' => $row['label'],
            'total' => (int) $row['total'],
        ], $rows);
    }
    /**
     * Une consultation est payante si elle a un ticket (patient non assuré)
     * ou si la facture assurance a été ouverte comme payante.
     * La facture d'actes ne compte pas : une consultation gratuite peut en avoir une.
     */

    private function countPaidConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $medecin = null): int
    {
        return $this->queries->countPaidConsultations($from, $to, $medecin);
    }

    private function computeWaitStats(array $consultations): array
    {
        $now = $this->clock->now();
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
        $transactions = $this->queries->transactions($from, $to, 'Revenue');
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
            $modeRows[] = [
                'label' => $label,
                'total' => $total,
            ];
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
        $transactions = $this->queries->transactions($from, $to);
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
                $daily[$date] = [
                    'entries' => 0.0,
                    'exits' => 0.0,
                ];
            }
            $amount = (float) $tx->getMontant();
            if ($tx->getType() === 'Revenue') {
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
        return $this->queries->doctorPayments($medecin, $from, $to);
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

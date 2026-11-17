<?php

namespace App\Billing\Application\Handler;

use DateTime;
use DateTimeImmutable;
use App\Billing\Domain\Entity\Transaction;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;
use App\Reporting\Application\Service\ReportService;
use App\Settings\Application\Service\GlobalSettingsService;
use App\Billing\Application\Policy\TransactionTypePolicy;
use App\Billing\Application\Mapper\FinanceTransactionMapper;

class FinanceCrossTableQueryHandler
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private ReportService $reportService,
        private GlobalSettingsService $globalSettingsService,
        private FinanceTransactionMapper $transactionMapper,
        private TransactionTypePolicy $typePolicy,
    ) {
    }

    public function getCrossTableDayOverview(string $date): array
    {
        return $this->getCrossTablePeriodOverview($date, $date);
    }

    public function getCrossTablePeriodOverview(string $fromDate, string $toDate): array
    {
        $from = DateTimeImmutable::createFromFormat('Y-m-d', $fromDate)?->setTime(0, 0, 0);
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $toDate)?->setTime(23, 59, 59);
        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable) {
            throw new \InvalidArgumentException('Date invalide.');
        }
        if ($from > $to) {
            throw new \InvalidArgumentException('La date de début doit être antérieure ou égale à la date de fin.');
        }

        $fromMutable = DateTime::createFromImmutable($from);
        $toMutable = DateTime::createFromImmutable($to);

        $typeAliases = array_merge(
            $this->typePolicy->resolveTransactionTypeAliases('revenue'),
            $this->typePolicy->resolveTransactionTypeAliases('expense'),
        );
        $validatedTransactions = $this->transactionRepo->findValidatedBetweenByTypes($from, $to, $typeAliases);

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;
        $mappedTransactions = [];
        foreach ($validatedTransactions as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }

            $row = $this->transactionMapper->mapTransactionToArray($transaction);
            $mappedTransactions[] = $row;
            $amount = (float) ($transaction->getMontant() ?? 0);
            if ($row['typeKey'] === 'revenue') {
                $revenueTotal += $amount;
            } elseif ($row['typeKey'] === 'expense') {
                $expenseTotal += $amount;
            }
        }

        $doctorReports = $this->reportService->periodicDoctorReports($from, $to);
        $actes = [];
        foreach ($doctorReports['doctors'] ?? [] as $doctor) {
            foreach ($doctor['actes'] ?? [] as $acte) {
                $actes[] = [
                    'date' => $acte['date'] ?? '',
                    'medecin' => $doctor['name'] ?? '',
                    'patient' => $acte['patient'] ?? '',
                    'description' => $acte['description'] ?? '',
                    'montant' => $acte['montant'] ?? 0,
                ];
            }
        }

        $actsStatsPayload = $this->reportService->periodicActsStats($fromMutable, $toMutable);
        $actsByType = [];
        foreach (($actsStatsPayload['categories'] ?? []) as $category) {
            foreach (($category['items'] ?? []) as $item) {
                $actsByType[] = [
                    'label' => (string) ($item['label'] ?? ''),
                    'value' => (int) ($item['value'] ?? 0),
                ];
            }
        }
        usort($actsByType, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $patientStats = $this->reportService->periodicPatients($fromMutable, $toMutable);
        $appointmentStats = $this->reportService->periodicAppointments($fromMutable, $toMutable);
        $consultStats = $this->reportService->periodicConsultations($fromMutable, $toMutable);
        $receptionStats = $this->reportService->getReceptionStats($from, $to);

        $dateLabel = $fromDate === $toDate
            ? $from->format('d/m/Y')
            : sprintf('%s - %s', $from->format('d/m/Y'), $to->format('d/m/Y'));

        return [
            'date' => $fromDate,
            'toDate' => $toDate,
            'dateLabel' => $dateLabel,
            'transactions' => $mappedTransactions,
            'totals' => [
                'revenue' => round($revenueTotal, 2),
                'expense' => round($expenseTotal, 2),
            ],
            'patients' => $patientStats,
            'appointments' => $appointmentStats,
            'consultations' => [
                'total' => $consultStats['total'] ?? 0,
                'paid' => $consultStats['paid'] ?? 0,
                'free' => $consultStats['free'] ?? 0,
                'pending' => $receptionStats['pendingConsultations'] ?? 0,
            ],
            'actes' => $actes,
            'actsByType' => $actsByType,
            'doctors' => $doctorReports['doctors'] ?? [],
            'doctorsKpi' => $doctorReports['kpi'] ?? [],
        ];
    }

    public function getMonthlyCrossTable(int $year, int $month, string $type = 'revenue'): array
    {
        $month = max(1, min(12, $month));
        $typeKey = $this->typePolicy->resolveTransactionTypeKey($type);
        $from = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $to = $from->modify('last day of this month')->setTime(23, 59, 59);
        $lastDay = (int) $to->format('j');
        $weeksCount = (int) ceil($lastDay / 7);
        $weeks = [];

        for ($index = 1; $index <= $weeksCount; $index++) {
            $startDay = (($index - 1) * 7) + 1;
            $endDay = min($index * 7, $lastDay);
            $weeks[] = [
                'index' => $index,
                'label' => sprintf('Semaine %d', $index),
                'startDate' => sprintf('%04d-%02d-%02d', $year, $month, $startDay),
                'endDate' => sprintf('%04d-%02d-%02d', $year, $month, $endDay),
            ];
        }

        $rows = [];
        $grid = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $grid[$weekday] = array_fill(1, $weeksCount, 0.0);
        }

        $transactions = $this->transactionRepo->findValidatedBetweenByTypes($from, $to, $this->typePolicy->resolveTransactionTypeAliases($typeKey));
        foreach ($transactions as $transaction) {
            if (!$transaction->isValidated()) continue;
            if (!$transaction instanceof Transaction) {
                continue;
            }

            $validatedAt = $transaction->getValidatedAt();
            if (!$validatedAt instanceof DateTimeImmutable) {
                continue;
            }

            $weekday = (int) $validatedAt->format('N');
            $weekIndex = (int) floor(((int) $validatedAt->format('j') - 1) / 7) + 1;
            if (!isset($grid[$weekday][$weekIndex])) {
                continue;
            }

            $grid[$weekday][$weekIndex] += (float) ($transaction->getMontant() ?? 0);
        }

        $columnTotals = array_fill(1, $weeksCount, 0.0);
        $grandTotal = 0.0;
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $values = [];
            $rowTotal = 0.0;

            for ($weekIndex = 1; $weekIndex <= $weeksCount; $weekIndex++) {
                $amount = (float) $grid[$weekday][$weekIndex];
                $values[] = $amount;
                $rowTotal += $amount;
                $columnTotals[$weekIndex] += $amount;
            }

            $grandTotal += $rowTotal;
            $rows[] = [
                'weekday' => $weekday,
                'label' => $this->weekdayLabel($weekday),
                'values' => $values,
                'total' => $rowTotal,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'type' => $typeKey,
            'typeLabel' => $typeKey === 'expense' ? 'Dépenses' : 'Revenus',
            'monthLabel' => ucfirst((new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM yyyy'))->format($from)),
            'weeks' => $weeks,
            'rows' => $rows,
            'columnTotals' => array_values($columnTotals),
            'grandTotal' => $grandTotal,
            'availableTypes' => [
                ['label' => 'Revenus', 'value' => 'revenue'],
                ['label' => 'Dépenses', 'value' => 'expense'],
            ],
            'transactionMotifs' => $this->globalSettingsService->getTransactionMotifs(),
        ];
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
            default => 'Inconnu',
        };
    }
}

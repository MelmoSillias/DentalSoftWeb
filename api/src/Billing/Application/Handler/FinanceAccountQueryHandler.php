<?php

namespace App\Billing\Application\Handler;

use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;

class FinanceAccountQueryHandler
{
    public function __construct(
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
    ) {
    }

    public function computeMonthlySummary(array $transactions): array
    {
        $monthlyRevenues = array_fill(0, 12, 0.0);
        $monthlyExpenses = array_fill(0, 12, 0.0);

        foreach ($transactions as $transaction) {
            if (!$transaction->isValidated()) continue;
            $month = (int) $transaction->getDateTransaction()->format('n') - 1;
            if ($transaction->getType() === 'Revenue') {
                $monthlyRevenues[$month] += $transaction->getMontant();
            } else {
                $monthlyExpenses[$month] += $transaction->getMontant();
            }
        }

        $monthlyProfits = [];
        for ($i = 0; $i < 12; $i++) {
            $monthlyProfits[$i] = $monthlyRevenues[$i] - $monthlyExpenses[$i];
        }

        return [
            'monthlyRevenues' => $monthlyRevenues,
            'monthlyExpenses' => $monthlyExpenses,
            'monthlyProfits'  => $monthlyProfits,
        ];
    }

    public function getAvailableTransactionYears(): array
    {
        $years = [];

        foreach ($this->transactionRepo->findAll() as $transaction) {
            $years[] = (int) $transaction->getDateTransaction()->format('Y');
        }

        $years = array_values(array_unique($years));
        rsort($years);

        if (empty($years)) {
            return [(int) date('Y')];
        }

        return $years;
    }

    public function getBarParCompteAnnuel(?int $year = null): array
    {
        $targetYear = (string) ($year ?? (int) date('Y'));
        $comptes = $this->modeRepo->findActifs();
        $datasets = [
            'labels'   => [],
            'entrees'  => [],
            'depenses' => [],
            'soldes'   => [],
        ];

        foreach ($comptes as $mode) {
            $entree = 0;
            $sortie = 0;

            foreach ($mode->getTransactions() as $t) {
                if (!$t->isValidated()) continue;
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                if ($t->getType() === 'Revenue') {
                    $entree += $t->getMontant();
                } else {
                    $sortie += $t->getMontant();
                }
            }

            $datasets['labels'][]   = $mode->getLibelle();
            $datasets['entrees'][]  = $entree;
            $datasets['depenses'][] = $sortie;
            $datasets['soldes'][]   = $entree - $sortie;
        }

        return $datasets;
    }

    public function getBarPointChartData(?int $year = null): array
    {
        $targetYear = (string) ($year ?? (int) date('Y'));
        $comptes = $this->modeRepo->findActifs();
        $labels = [];
        $entrees = [];
        $depenses = [];
        $soldes = [];
        $colors = [];

        $colorPalette = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8'];
        $i = 0;

        foreach ($comptes as $mode) {
            $totalIn = 0;
            $totalOut = 0;

            foreach ($mode->getTransactions() as $t) {
                if (!$t->isValidated()) continue;
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                if ($t->getType() === 'Revenue') {
                    $totalIn += $t->getMontant();
                } else {
                    $totalOut += $t->getMontant();
                }
            }

            $labels[] = $mode->getLibelle();
            $entrees[] = $totalIn;
            $depenses[] = $totalOut;
            $soldes[] = $totalIn - $totalOut;
            $colors[] = $colorPalette[$i++ % count($colorPalette)];
        }

        return [
            'labels'   => $labels,
            'entrees'  => $entrees,
            'depenses' => $depenses,
            'soldes'   => $soldes,
            'colors'   => $colors,
        ];
    }

    public function getEvolutionCapitalAnnuel(?int $year = null): array
    {
        $targetYear = (string) ($year ?? (int) date('Y'));
        $evolution = array_fill(0, 12, 0);
        $cumul = 0;

        foreach ($this->transactionRepo->findAll() as $t) {
            if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                continue;
            }

            $month = (int) $t->getDateTransaction()->format('n') - 1;
            $cumul += ($t->getType() === 'Revenue') ? $t->getMontant() : -$t->getMontant();
            $evolution[$month] = $cumul;
        }

        return $evolution;
    }

    public function getGraphDatasetsParCompteComplet(?int $year = null): array
    {
        $targetYear = (string) ($year ?? (int) date('Y'));
        $colorMap = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8'];

        $datasets = [];
        $modes = $this->modeRepo->findActifs();
        $colorIndex = 0;

        foreach ($modes as $mode) {
            $entrees = array_fill(0, 12, 0);
            $sorties = array_fill(0, 12, 0);
            $soldes  = array_fill(0, 12, 0);

            foreach ($mode->getTransactions() as $t) {
                if (!$t->isValidated()) continue;
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                $mois = (int) $t->getDateTransaction()->format('n') - 1;
                if ($t->getType() === 'Revenue') {
                    $entrees[$mois] += $t->getMontant();
                } elseif ($t->getType() === 'Depense') {
                    $sorties[$mois] += $t->getMontant();
                }
            }

            for ($i = 0; $i < 12; $i++) {
                $soldes[$i] = $entrees[$i] - $sorties[$i];
            }

            $mainColor = $colorMap[$colorIndex % count($colorMap)];
            $colorIndex++;

            $datasets[] = [
                'label' => $mode->getLibelle() . ' - Entrées',
                'data' => $entrees,
                'type' => 'bar',
                'backgroundColor' => $mainColor,
                'stack' => $mode->getId(),
            ];
            $datasets[] = [
                'label' => $mode->getLibelle() . ' - Dépenses',
                'data' => $sorties,
                'type' => 'bar',
                'backgroundColor' => $mainColor . '99',
                'stack' => $mode->getId(),
            ];
            $datasets[] = [
                'label' => $mode->getLibelle() . ' - Solde',
                'data' => $soldes,
                'type' => 'line',
                'borderColor' => $mainColor,
                'backgroundColor' => $mainColor . '33',
                'tension' => 0.3,
                'fill' => false,
                'borderWidth' => 2,
            ];
        }

        return $datasets;
    }

    public function getSoldesParCompte(): array
    {
        $soldes = [];

        foreach ($this->modeRepo->findActifs() as $mode) {
            $solde = 0;
            foreach ($mode->getTransactions() as $transaction) {
                if (!$transaction->isValidated()) continue;
                if ($transaction->getType() === 'Revenue') {
                    $solde += $transaction->getMontant();
                } else {
                    $solde -= $transaction->getMontant();
                }
            }

            $soldes[] = [
                'id'      => $mode->getId(),
                'libelle' => $mode->getLibelle(),
                'type'    => $mode->getType(),
                'solde'   => $solde,
            ];
        }

        return $soldes;
    }
}

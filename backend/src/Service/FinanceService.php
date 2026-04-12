<?php

namespace App\Service;

use App\Entity\ModeDePaiement;
use App\Entity\PaiementDevis;
use App\Entity\Transaction;
use App\Repository\ModeDePaiementRepository;
use App\Repository\TransactionRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class FinanceService
{
    public function __construct(
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactionRepo->findBy([], ['dateTransaction' => 'DESC']);
    }

    public function computeMonthlySummary(array $transactions): array
    {
        $monthlyRevenues = array_fill(0, 12, 0.0);
        $monthlyExpenses = array_fill(0, 12, 0.0);

        foreach ($transactions as $transaction) {
            $month = (int) $transaction->getDateTransaction()->format('n') - 1;
            if ($transaction->getType() === 'Entrée') {
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
        $comptes = $this->modeRepo->findBy(['actif' => true]);
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
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                if ($t->getType() === 'Entrée') {
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
        $comptes = $this->modeRepo->findBy(['actif' => true]);
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
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                if ($t->getType() === 'Entrée') {
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
            $cumul += ($t->getType() === 'Entrée') ? $t->getMontant() : -$t->getMontant();
            $evolution[$month] = $cumul;
        }

        return $evolution;
    }

    public function getGraphDatasetsParCompteComplet(?int $year = null): array
    {
        $targetYear = (string) ($year ?? (int) date('Y'));
        $colorMap = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8'];

        $datasets = [];
        $modes = $this->modeRepo->findBy(['actif' => true]);
        $colorIndex = 0;

        foreach ($modes as $mode) {
            $entrees = array_fill(0, 12, 0);
            $sorties = array_fill(0, 12, 0);
            $soldes  = array_fill(0, 12, 0);

            foreach ($mode->getTransactions() as $t) {
                if ($t->getDateTransaction()->format('Y') !== $targetYear) {
                    continue;
                }

                $mois = (int) $t->getDateTransaction()->format('n') - 1;
                if ($t->getType() === 'Entrée') {
                    $entrees[$mois] += $t->getMontant();
                } elseif ($t->getType() === 'Sortie') {
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

        foreach ($this->modeRepo->findBy(['actif' => true]) as $mode) {
            $solde = 0;
            foreach ($mode->getTransactions() as $transaction) {
                if ($transaction->getType() === 'Entrée') {
                    $solde += $transaction->getMontant();
                } else {
                    $solde -= $transaction->getMontant();
                }
            }

            $soldes[] = [
                'id'      => $mode->getId(),
                'libelle' => $mode->getLibelle(),
                'type'    => $mode->getType(),
                'typeKey' => $mode->getTypeKey(),
                'family'  => $mode->getFamilyKey(),
                'coverageRate' => $mode->getCoverageRate(),
                'solde'   => $solde,
            ];
        }

        return $soldes;
    }

    public function createTransaction(string $type, float $montant, ?string $description, DateTimeInterface $date, int $modeId): ?Transaction
    {
        $mode = $this->modeRepo->find($modeId);
        if (!$mode) {
            return null;
        }

        $transaction = new Transaction();
        $transaction->setType($type === 'entry' ? 'Entrée' : 'Sortie');
        $transaction->setMontant($montant);
        $transaction->setDescription($description);
        $transaction->setDateTransaction($date);
        $transaction->setModeDePaiement($mode);

        if ($mode->isAutoValidated()) {
            $transaction->markValidated();
        } else {
            $transaction->markPending();
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    public function getTransactionsByDateRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->where('t.dateTransaction BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.dateTransaction', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Transaction $transaction) {
            return [
                'date' => $transaction->getDateTransaction()->format('Y-m-d'),
                'dateTransaction' => $transaction->getDateTransaction()->format('Y-m-d H:i:s'),
                'id' => $transaction->getId(),
                'description' => $transaction->getDescription(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getMontant(),
                'validated' => $transaction->isValidated(),
                'validationStatus' => $transaction->getValidationStatus(),
                'validationComment' => $transaction->getValidationComment(),
                'modeDePaiement' => [
                    'id' => $transaction->getModeDePaiement()->getId(),
                    'libelle' => $transaction->getModeDePaiement()->getLibelle(),
                    'type' => $transaction->getModeDePaiement()->getType(),
                    'typeKey' => $transaction->getModeDePaiement()->getTypeKey(),
                    'family' => $transaction->getModeDePaiement()->getFamilyKey(),
                    'coverageRate' => $transaction->getModeDePaiement()->getCoverageRate(),
                ],
            ];
        }, $transactions);
    }

    public function listModes(): array
    {
        $modes = $this->modeRepo->findAll();

        return array_map(function (ModeDePaiement $mode) {
            return [
                'id' => $mode->getId(),
                'libelle' => $mode->getLibelle(),
                'type' => $mode->getType(),
                'typeKey' => $mode->getTypeKey(),
                'family' => $mode->getFamilyKey(),
                'coverageRate' => $mode->getCoverageRate(),
                'actif' => $mode->isActif(),
                'notes' => $mode->getNotes(),
            ];
        }, $modes);
    }

    public function createMode(array $data): ModeDePaiement
    {
        $mode = new ModeDePaiement();
        $mode->setLibelle($data['libelle'] ?? '');
        $mode->setType($data['type'] ?? '');
        $mode->setTypeKey($data['typeKey'] ?? null);
        $mode->setFamilyKey($data['family'] ?? 'classic');
        $mode->setCoverageRate(isset($data['coverageRate']) ? (float) $data['coverageRate'] : null);
        $mode->setNotes($data['notes'] ?? null);
        $mode->setActif(true);

        $this->em->persist($mode);
        $this->em->flush();

        return $mode;
    }

    public function deleteMode(ModeDePaiement $mode): void
    {
        $this->em->remove($mode);
        $this->em->flush();
    }

    public function toggleMode(ModeDePaiement $mode): bool
    {
        $mode->setActif(!$mode->isActif());
        $this->em->flush();

        return $mode->isActif();
    }

    public function transferInterCompte(int $fromId, int $toId, float $montant, string $motif, DateTimeInterface $date): array
    {
        $from = $this->modeRepo->find($fromId);
        $to = $this->modeRepo->find($toId);

        if (!$from || !$to || $from === $to) {
            return ['error' => 'Comptes invalides'];
        }

        $tOut = new Transaction();
        $tOut->setType('Sortie');
        $tOut->setMontant($montant);
        $tOut->setDateTransaction($date);
        $tOut->setDescription("[Transfert] vers {$to->getLibelle()} - {$motif}");
        $tOut->setModeDePaiement($from);
        $this->em->persist($tOut);

        $tIn = new Transaction();
        $tIn->setType('Entrée');
        $tIn->setMontant($montant);
        $tIn->setDateTransaction($date);
        $tIn->setDescription("[Transfert] depuis {$from->getLibelle()} - {$motif}");
        $tIn->setModeDePaiement($to);
        $this->em->persist($tIn);

        $this->em->flush();

        return ['message' => 'Transfert effectué avec succès'];
    }

    public function updateTransactionValidationStatus(int $id, string $status, ?string $comment = null): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if ($status === 'validated') {
            $transaction->markValidated();
            $transaction->setValidationComment(null);

            if (
                $transaction->getPaiementDevis() === null
                && $transaction->getType() === 'Entrée'
                && $transaction->getRolePaiement() === 'insurance'
            ) {
                $paiement = new PaiementDevis();
                $paiement->setMode($transaction->getModeDePaiement());
                $paiement->setMontant((float) ($transaction->getMontant() ?? 0));
                $paiement->setDate($transaction->getDateTransaction() ?? new \DateTime());
                $paiement->setRolePaiement($transaction->getRolePaiement());
                $paiement->setTauxPriseEnCharge($transaction->getTauxPriseEnCharge());

                if ($transaction->getDevis()) {
                    $paiement->setDevis($transaction->getDevis());
                }

                if ($transaction->getConsultation()) {
                    $paiement->setConsultation($transaction->getConsultation());
                }

                $transaction->setPaiementDevis($paiement);
                $this->em->persist($paiement);
            }
        } elseif ($status === 'rejected') {
            $transaction->markRejected($comment);
        } else {
            return ['error' => 'Statut de validation invalide', 'status' => 400];
        }

        $this->em->flush();

        return ['success' => true];
    }
}

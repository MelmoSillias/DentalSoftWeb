<?php

namespace App\Service;

use App\Entity\ChargeFixe;
use App\Entity\Devis;
use App\Entity\ModeDePaiement;
use App\Entity\PaiementDevis;
use App\Entity\Transaction;
use App\Repository\ChargeFixeRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\TransactionRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class FinanceService
{
    public function __construct(
        private ChargeFixeRepository $chargeFixeRepo,
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
        private EntityManagerInterface $em,
        private GlobalSettingsService $globalSettingsService,
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

    public function listFixedCharges(): array
    {
        $charges = $this->chargeFixeRepo->findBy([], ['designation' => 'ASC']);

        return array_map(fn (ChargeFixe $charge) => $this->mapFixedCharge($charge), $charges);
    }

    public function createFixedCharge(array $data): array
    {
        $designation = trim((string) ($data['designation'] ?? ''));
        $montant = (float) ($data['montant'] ?? 0);

        if ($designation === '') {
            return ['error' => 'La désignation est requise.', 'status' => 400];
        }

        if ($montant <= 0) {
            return ['error' => 'Le montant doit être supérieur à 0.', 'status' => 400];
        }

        $charge = new ChargeFixe();
        $charge->setDesignation($designation);
        $charge->setMontant($montant);

        $this->em->persist($charge);
        $this->em->flush();

        return $this->mapFixedCharge($charge);
    }

    public function updateFixedCharge(int $id, array $data): array
    {
        $charge = $this->chargeFixeRepo->find($id);
        if (!$charge) {
            return ['error' => 'Charge fixe introuvable.', 'status' => 404];
        }

        $designation = trim((string) ($data['designation'] ?? ''));
        $montant = (float) ($data['montant'] ?? 0);

        if ($designation === '') {
            return ['error' => 'La désignation est requise.', 'status' => 400];
        }

        if ($montant <= 0) {
            return ['error' => 'Le montant doit être supérieur à 0.', 'status' => 400];
        }

        $charge->setDesignation($designation);
        $charge->setMontant($montant);

        $this->em->flush();

        return $this->mapFixedCharge($charge);
    }

    public function deleteFixedCharge(int $id): array
    {
        $charge = $this->chargeFixeRepo->find($id);
        if (!$charge) {
            return ['error' => 'Charge fixe introuvable.', 'status' => 404];
        }

        $this->em->remove($charge);
        $this->em->flush();

        return ['success' => true];
    }

    public function getFixedChargesTotal(): float
    {
        return array_reduce(
            $this->chargeFixeRepo->findAll(),
            static fn (float $sum, ChargeFixe $charge): float => $sum + (float) ($charge->getMontant() ?? 0),
            0.0
        );
    }

    public function createTransaction(string $type, float $montant, ?string $description, DateTimeInterface $date, int $modeId, ?string $motif = null): array
    {
        $mode = $this->modeRepo->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $transaction = new Transaction();
        $transaction->setType($this->normalizePersistedTransactionType($type));
        $transaction->setMontant($montant);
        $transaction->setDescription($description);
        $transaction->setMotif($motif);
        $transaction->setDateTransaction($date);
        $transaction->setModeDePaiement($mode);

        if ($mode->isAutoValidated()) {
            $transaction->markValidated();
        } else {
            $transaction->markPending();
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return [
            'success' => true,
            'transactionId' => $transaction->getId(),
        ];
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
            $typeKey = $this->resolveTransactionTypeKey($transaction->getType());

            return [
                'date' => $transaction->getDateTransaction()->format('Y-m-d'),
                'dateTransaction' => $transaction->getDateTransaction()->format('Y-m-d H:i:s'),
                'id' => $transaction->getId(),
                'description' => $transaction->getDescription(),
                'motif' => $transaction->getMotif(),
                'type' => $transaction->getType(),
                'typeKey' => $typeKey,
                'typeLabel' => $typeKey === 'revenue' ? 'Revenu' : ($typeKey === 'expense' ? 'Dépense' : ($transaction->getType() ?? '')), 
                'amount' => $transaction->getMontant(),
                'validated' => $transaction->isValidated(),
                'validationStatus' => $transaction->getValidationStatus(),
                'validationComment' => $transaction->getValidationComment(),
                'validatedAt' => $transaction->getValidatedAt()?->format(DATE_ATOM),
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

    public function updateTransactionValidationStatus(int $id, string $status, ?string $comment = null, ?DateTimeImmutable $validatedAt = null): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if ($status === 'validated') {
            $transaction->markValidated($validatedAt);
            $transaction->setValidationComment(null);

            if (
                $transaction->getPaiementDevis() === null
                && $transaction->getType() === 'Entrée'
                && $transaction->getRolePaiement() === 'insurance'
            ) {
                $paiement = new PaiementDevis();
                $paiement->setMode($transaction->getModeDePaiement());
                $paiement->setMontant((float) ($transaction->getMontant() ?? 0));
                $paiement->setDate($validatedAt ? DateTime::createFromImmutable($validatedAt) : ($transaction->getDateTransaction() ?? new DateTime()));
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
            $devis = $this->detachTransactionPayment($transaction);
            $transaction->markRejected($comment);
            $this->recalculateDevisBalance($devis);
        } else {
            return ['error' => 'Statut de validation invalide', 'status' => 400];
        }

        $this->em->flush();

        return ['success' => true];
    }

    public function deleteTransaction(int $id): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        $devis = $this->detachTransactionPayment($transaction);
        if ($devis === null) {
            $consultation = $transaction->getConsultation();
            $devis = $transaction->getDevis() ?: $consultation?->getFacture();
        }

        $transaction->setConsultation(null);
        $transaction->setDevis(null);
        $this->em->remove($transaction);

        $this->recalculateDevisBalance($devis);
        $this->em->flush();

        return ['success' => true];
    }

    public function getMonthlyCrossTable(int $year, int $month, string $type = 'revenue'): array
    {
        $month = max(1, min(12, $month));
        $typeKey = $this->resolveTransactionTypeKey($type);
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

        $transactions = $this->transactionRepo->findValidatedBetweenByTypes($from, $to, $this->resolveTransactionTypeAliases($typeKey));
        foreach ($transactions as $transaction) {
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

    private function detachTransactionPayment(Transaction $transaction): ?Devis
    {
        $paiement = $transaction->getPaiementDevis();
        $devis = $transaction->getDevis();

        if (!$paiement instanceof PaiementDevis) {
            return $devis ?: $transaction->getConsultation()?->getFacture();
        }

        $devis = $devis ?: $paiement->getDevis() ?: $transaction->getConsultation()?->getFacture();
        $consultation = $transaction->getConsultation();

        if ($consultation && $consultation->getPaiementDevis() === $paiement) {
            $consultation->setPaiementDevis(null);
        }

        if ($devis && $devis->getPaiements()->contains($paiement)) {
            $devis->removePaiement($paiement);
        }

        $transaction->setPaiementDevis(null);
        $paiement->setConsultation(null);
        $paiement->setDevis(null);
        $this->em->remove($paiement);

        return $devis;
    }

    private function recalculateDevisBalance(?Devis $devis): void
    {
        if (!$devis instanceof Devis) {
            return;
        }

        $paidAmount = 0.0;
        foreach ($devis->getPaiements() as $paiement) {
            $paidAmount += (float) ($paiement->getMontant() ?? 0);
        }

        $montant = (float) ($devis->getMontant() ?? 0);
        $reste = max(0.0, $montant - $paidAmount);
        $devis->setReste($reste);
        $devis->setStatut($reste <= 0.0 ? 1 : 0);
        $this->em->persist($devis);
    }

    private function resolveTransactionTypeKey(?string $type): string
    {
        $value = strtolower(trim((string) $type));
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç', ' '], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c', '_'], $value);

        return match (true) {
            in_array($value, ['entry', 'income', 'revenue', 'revenu', 'entree'], true) => 'revenue',
            in_array($value, ['exit', 'expense', 'depense', 'sortie'], true) => 'expense',
            default => 'other',
        };
    }

    /** @return string[] */
    private function resolveTransactionTypeAliases(string $typeKey): array
    {
        return match ($typeKey) {
            'expense' => ['Sortie'],
            default => ['Entrée'],
        };
    }

    private function normalizePersistedTransactionType(string $type): string
    {
        return $this->resolveTransactionTypeKey($type) === 'expense' ? 'Sortie' : 'Entrée';
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

    private function mapFixedCharge(ChargeFixe $charge): array
    {
        return [
            'id' => $charge->getId(),
            'designation' => $charge->getDesignation(),
            'montant' => (float) ($charge->getMontant() ?? 0),
        ];
    }
}

<?php

namespace App\Billing\Application\Service;

use App\Billing\Domain\Entity\ChargeFixe;
use App\Billing\Domain\Entity\ModeDePaiement;
use App\Billing\Infrastructure\Persistence\Doctrine\ChargeFixeRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

use App\Billing\Application\Handler\FinanceAccountQueryHandler;
use App\Billing\Application\Handler\FinanceCrossTableQueryHandler;
use App\Billing\Application\Handler\FinanceTransactionQueryHandler;
use App\Billing\Application\UseCase\ManageFinanceTransactions;

/** Compatibility facade for finance callers. */
class FinanceService
{
    public function __construct(
        private ChargeFixeRepository $chargeFixeRepo,
        private ModeDePaiementRepository $modeRepo,
        private EntityManagerInterface $em,
        private FinanceAccountQueryHandler $accountQueries,
        private FinanceCrossTableQueryHandler $crossTableQueries,
        private FinanceTransactionQueryHandler $transactionQueries,
        private ManageFinanceTransactions $transactionUseCase,
    ) {
    }

    public function getTransactions(): array
    {
        return $this->transactionQueries->getTransactions();
    }

    public function computeMonthlySummary(array $transactions): array
    {
        return $this->accountQueries->computeMonthlySummary($transactions);
    }

    public function getAvailableTransactionYears(): array
    {
        return $this->accountQueries->getAvailableTransactionYears();
    }

    public function getBarParCompteAnnuel(?int $year = null): array
    {
        return $this->accountQueries->getBarParCompteAnnuel($year);
    }

    public function getBarPointChartData(?int $year = null): array
    {
        return $this->accountQueries->getBarPointChartData($year);
    }

    public function getEvolutionCapitalAnnuel(?int $year = null): array
    {
        return $this->accountQueries->getEvolutionCapitalAnnuel($year);
    }

    public function getGraphDatasetsParCompteComplet(?int $year = null): array
    {
        return $this->accountQueries->getGraphDatasetsParCompteComplet($year);
    }

    public function getSoldesParCompte(): array
    {
        return $this->accountQueries->getSoldesParCompte();
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
            return ['error' => 'Le montant doit Ãªtre supérieur Ã  0.', 'status' => 400];
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
            return ['error' => 'Le montant doit Ãªtre supérieur Ã  0.', 'status' => 400];
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
        return $this->transactionUseCase->createTransaction($type, $montant, $description, $date, $modeId, $motif);
    }

    public function updateTransaction(int $id, string $type, float $montant, ?string $description, DateTimeInterface $date, int $modeId, ?string $motif = null): array
    {
        return $this->transactionUseCase->updateTransaction($id, $type, $montant, $description, $date, $modeId, $motif);
    }

    public function getTransactionsByDateRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->transactionQueries->getTransactionsByDateRange($start, $end);
    }

    public function getCrossTableDayOverview(string $date): array
    {
        return $this->crossTableQueries->getCrossTableDayOverview($date);
    }

    public function getCrossTablePeriodOverview(string $fromDate, string $toDate): array
    {
        return $this->crossTableQueries->getCrossTablePeriodOverview($fromDate, $toDate);
    }

    public function listModes(): array
    {
        $modes = $this->modeRepo->findAll();

        return array_map(function (ModeDePaiement $mode) {
            return [
                'id' => $mode->getId(),
                'libelle' => $mode->getLibelle(),
                'type' => $mode->getType(),
                'actif' => $mode->isActif(),
                'notes' => $mode->getNotes(),
            ];
        }, $modes);
    }

    public function createMode(array $data): ModeDePaiement
    {
        $mode = new ModeDePaiement();
        $mode->setLibelle($data['libelle'] ?? '');
        $mode->setType($data['type'] ?? 'cash');
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
        return $this->transactionUseCase->transferInterCompte($fromId, $toId, $montant, $motif, $date);
    }

    public function updateTransactionValidationStatus(int $id, string $status, ?string $comment = null, ?DateTimeImmutable $validatedAt = null): array
    {
        return $this->transactionUseCase->updateTransactionValidationStatus($id, $status, $comment, $validatedAt);
    }

    public function deleteTransaction(int $id): array
    {
        return $this->transactionUseCase->deleteTransaction($id);
    }

    public function getMonthlyCrossTable(int $year, int $month, string $type = 'revenue'): array
    {
        return $this->crossTableQueries->getMonthlyCrossTable($year, $month, $type);
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

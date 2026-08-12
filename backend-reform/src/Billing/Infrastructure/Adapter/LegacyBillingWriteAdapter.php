<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\BillingWritePort;
use App\Billing\Application\Port\ClassicBillingPort;
use App\Billing\Application\Port\InsuredBillingPort;
use App\Billing\Service\FinanceService;
use App\ClinicalRecord\Service\FicheMedicaleService;
use DateTimeImmutable;
use DateTimeInterface;

final class LegacyBillingWriteAdapter implements BillingWritePort
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly ClassicBillingPort $classicBilling,
        private readonly InsuredBillingPort $insuredBilling,
        private readonly FicheMedicaleService $ficheMedicaleService,
    ) {
    }

    public function createTransaction(
        string $type,
        float $montant,
        ?string $description,
        DateTimeInterface $date,
        int $modeId,
        ?string $motif = null,
    ): array {
        return $this->financeService->createTransaction(
            $type,
            $montant,
            $description,
            $date,
            $modeId,
            $motif,
        );
    }

    public function updateTransactionValidationStatus(
        int $id,
        string $status,
        ?string $comment = null,
        ?DateTimeImmutable $validatedAt = null,
    ): array {
        return $this->financeService->updateTransactionValidationStatus($id, $status, $comment, $validatedAt);
    }

    public function deleteTransaction(int $id): array
    {
        return $this->financeService->deleteTransaction($id);
    }

    public function transferInterCompte(
        int $fromId,
        int $toId,
        float $montant,
        string $motif,
        DateTimeInterface $date,
    ): array {
        return $this->financeService->transferInterCompte($fromId, $toId, $montant, $motif, $date);
    }

    public function payerFacture(int $id, array $payload = []): array
    {
        return $this->classicBilling->payerFacture($id, $payload);
    }

    public function resetFacturePayments(int $id): array
    {
        return $this->classicBilling->resetFacturePayments($id);
    }

    public function payPatientShare(
        int $factureId,
        int $modeId,
        ?float $amount = null,
        ?DateTimeInterface $date = null,
    ): array {
        return $this->insuredBilling->payPatientShare($factureId, $modeId, $amount, $date);
    }

    public function resetFactureAssurancePayments(int $factureId): array
    {
        return $this->insuredBilling->resetPayments($factureId);
    }

    public function updateDevis(int $ficheId, array $data): void
    {
        $this->ficheMedicaleService->updateDevis($ficheId, $data);
    }
}

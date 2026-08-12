<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\BillingReadPort;
use App\Billing\Application\Port\ClassicBillingPort;
use App\Billing\Application\Port\InsuredBillingPort;
use App\Billing\Service\CashdeskEntryPointService;
use App\Billing\Service\FinanceService;
use DateTimeInterface;

final class LegacyBillingReadAdapter implements BillingReadPort
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly CashdeskEntryPointService $cashdeskEntryPoint,
        private readonly ClassicBillingPort $classicBilling,
        private readonly InsuredBillingPort $insuredBilling,
    ) {
    }

    public function getTransactions(): array
    {
        return $this->financeService->getTransactionsByDateRange(
            new \DateTime('1970-01-01 00:00:00'),
            new \DateTime('2999-12-31 23:59:59'),
        );
    }

    public function getTransactionsByDateRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->financeService->getTransactionsByDateRange($start, $end);
    }

    public function listModes(): array
    {
        return $this->financeService->listModes();
    }

    public function listAllFactures(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->cashdeskEntryPoint->listAllFactures($start, $end)->toArray();
    }

    public function listFacturesClassiques(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->classicBilling->listFacturesByPeriod($start, $end);
    }

    public function listFacturesImpayees(?DateTimeInterface $start, ?DateTimeInterface $end): array
    {
        return $this->cashdeskEntryPoint->getClassicWorkflow()->listFacturesImpayees($start, $end);
    }

    public function listPaiementsFactures(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->cashdeskEntryPoint->listPaiementsFactures($start, $end);
    }

    public function previewDevis(int $id): ?array
    {
        return $this->cashdeskEntryPoint->getClassicWorkflow()->previewDevis($id);
    }

    public function previewFacture(int $id): ?array
    {
        return $this->classicBilling->previewFacture($id);
    }

    public function previewFactureDetail(int $id): ?array
    {
        return $this->classicBilling->previewFactureDetail($id);
    }

    public function listFacturesAssurance(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?string $status = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
    ): array {
        return $this->insuredBilling->listFacturesAssurance(
            $start,
            $end,
            $status,
            $patientQuery,
            $assuranceCode,
        );
    }

    public function getClaimDetail(int $factureId): array
    {
        return $this->insuredBilling->getClaimDetail($factureId);
    }

    public function mapFactureAssurancePrint(int $id): ?array
    {
        return $this->insuredBilling->mapFactureAssurancePrint($id);
    }
}

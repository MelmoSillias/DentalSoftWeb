<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Billing\Service\Workflow\InsuredInvoiceWorkflowService;
use DateTimeInterface;

final class LegacyInsuredBillingAdapter implements InsuredBillingPort
{
    public function __construct(
        private readonly InsuredInvoiceWorkflowService $insuredWorkflow,
    ) {
    }

    public function listFacturesAssurance(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?string $status = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
    ): array {
        return $this->insuredWorkflow->listFacturesAssurance(
            $start,
            $end,
            $status,
            $patientQuery,
            $assuranceCode,
        );
    }

    public function getClaimDetail(int $factureId): array
    {
        return $this->insuredWorkflow->getClaimDetail($factureId);
    }

    public function payPatientShare(
        int $factureId,
        int $modeId,
        ?float $amount = null,
        ?DateTimeInterface $date = null,
    ): array {
        return $this->insuredWorkflow->payPatientShare($factureId, $modeId, $amount, $date);
    }

    public function resetPayments(int $factureId): array
    {
        return $this->insuredWorkflow->resetPayments($factureId);
    }

    public function mapFactureAssurancePrint(int $id): ?array
    {
        return $this->insuredWorkflow->mapFactureAssurancePrint($id);
    }
}

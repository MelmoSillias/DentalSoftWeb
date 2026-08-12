<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\ClassicBillingPort;
use App\Billing\Service\Workflow\ClassicInvoiceWorkflowService;
use DateTimeInterface;

final class LegacyClassicBillingAdapter implements ClassicBillingPort
{
    public function __construct(
        private readonly ClassicInvoiceWorkflowService $classicWorkflow,
    ) {
    }

    public function listFacturesByPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->classicWorkflow->listFacturesByPeriod($start, $end);
    }

    public function previewFactureDetail(int $id): ?array
    {
        return $this->classicWorkflow->previewFactureDetail($id);
    }

    public function previewFacture(int $id): ?array
    {
        return $this->classicWorkflow->previewFacture($id);
    }

    public function payerFacture(int $id, array $payload = []): array
    {
        return $this->classicWorkflow->payerFacture($id, $payload);
    }

    public function resetFacturePayments(int $id): array
    {
        return $this->classicWorkflow->resetFacturePayments($id);
    }
}

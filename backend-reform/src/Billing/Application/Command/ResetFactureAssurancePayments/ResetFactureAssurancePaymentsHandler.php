<?php

namespace App\Billing\Application\Command\ResetFactureAssurancePayments;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Shared\Application\Bus\CommandHandler;

final class ResetFactureAssurancePaymentsHandler implements CommandHandler
{
    public function __construct(private readonly InsuredBillingPort $insuredBilling)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ResetFactureAssurancePaymentsCommand $command): array
    {
        return $this->insuredBilling->resetPayments($command->factureId);
    }
}

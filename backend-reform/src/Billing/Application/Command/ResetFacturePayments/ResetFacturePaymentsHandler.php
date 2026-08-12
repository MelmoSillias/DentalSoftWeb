<?php

namespace App\Billing\Application\Command\ResetFacturePayments;

use App\Billing\Application\Port\ClassicBillingPort;
use App\Shared\Application\Bus\CommandHandler;

final class ResetFacturePaymentsHandler implements CommandHandler
{
    public function __construct(private readonly ClassicBillingPort $classicBilling)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ResetFacturePaymentsCommand $command): array
    {
        return $this->classicBilling->resetFacturePayments($command->factureId);
    }
}

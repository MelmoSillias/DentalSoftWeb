<?php

namespace App\Billing\Application\Command\PayPatientShare;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Shared\Application\Bus\CommandHandler;

final class PayPatientShareHandler implements CommandHandler
{
    public function __construct(private readonly InsuredBillingPort $insuredBilling)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(PayPatientShareCommand $command): array
    {
        return $this->insuredBilling->payPatientShare(
            $command->factureId,
            $command->modeId,
            $command->amount,
            $command->date,
        );
    }
}

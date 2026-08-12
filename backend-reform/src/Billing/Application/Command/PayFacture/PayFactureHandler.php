<?php

namespace App\Billing\Application\Command\PayFacture;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class PayFactureHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(PayFactureCommand $command): array
    {
        return $this->writePort->payerFacture($command->factureId, $command->payload);
    }
}

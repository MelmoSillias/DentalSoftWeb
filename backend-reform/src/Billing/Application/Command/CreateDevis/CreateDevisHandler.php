<?php

namespace App\Billing\Application\Command\CreateDevis;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateDevisHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    public function __invoke(CreateDevisCommand $command): void
    {
        $this->writePort->updateDevis($command->ficheId, $command->payload);
    }
}

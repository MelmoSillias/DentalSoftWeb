<?php

namespace App\Billing\Application\Command\UpdateDevis;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateDevisHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    public function __invoke(UpdateDevisCommand $command): void
    {
        $this->writePort->updateDevis($command->ficheId, $command->payload);
    }
}

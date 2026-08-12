<?php

namespace App\Billing\Application\Command\DeleteTransaction;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class DeleteTransactionHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeleteTransactionCommand $command): array
    {
        return $this->writePort->deleteTransaction($command->transactionId);
    }
}

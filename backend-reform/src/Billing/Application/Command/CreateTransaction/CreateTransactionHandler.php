<?php

namespace App\Billing\Application\Command\CreateTransaction;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateTransactionHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateTransactionCommand $command): array
    {
        return $this->writePort->createTransaction(
            $command->type,
            $command->montant,
            $command->description,
            $command->date,
            $command->modeId,
            $command->motif,
        );
    }
}

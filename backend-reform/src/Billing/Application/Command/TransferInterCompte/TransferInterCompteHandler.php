<?php

namespace App\Billing\Application\Command\TransferInterCompte;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class TransferInterCompteHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(TransferInterCompteCommand $command): array
    {
        return $this->writePort->transferInterCompte(
            $command->fromId,
            $command->toId,
            $command->montant,
            $command->motif,
            $command->date,
        );
    }
}

<?php

namespace App\Billing\Application\Command\RemoveClaimFromLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class RemoveClaimFromLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RemoveClaimFromLotCommand $command): array
    {
        return $this->lotPort->removeClaimFromLot($command->lotId, $command->factureId);
    }
}

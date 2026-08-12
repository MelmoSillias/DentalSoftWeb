<?php

namespace App\Billing\Application\Command\MoveClaimToLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class MoveClaimToLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(MoveClaimToLotCommand $command): array
    {
        return $this->lotPort->moveClaimToLot($command->factureId, $command->lotId);
    }
}

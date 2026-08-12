<?php

namespace App\Billing\Application\Command\AddClaimToLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class AddClaimToLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AddClaimToLotCommand $command): array
    {
        return $this->lotPort->addClaimToLot($command->lotId, $command->factureId);
    }
}

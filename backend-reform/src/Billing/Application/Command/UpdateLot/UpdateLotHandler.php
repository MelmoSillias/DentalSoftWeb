<?php

namespace App\Billing\Application\Command\UpdateLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateLotCommand $command): array
    {
        return $this->lotPort->updateLot(
            $command->lotId,
            $command->description,
            $command->dateDebut,
            $command->dateFin,
        );
    }
}

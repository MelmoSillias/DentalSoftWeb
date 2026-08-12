<?php

namespace App\Billing\Application\Command\OpenLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class OpenLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(OpenLotCommand $command): array
    {
        return $this->lotPort->openLot(
            $command->assuranceCode,
            $command->description,
            $command->dateDebut,
            $command->dateFin,
        );
    }
}

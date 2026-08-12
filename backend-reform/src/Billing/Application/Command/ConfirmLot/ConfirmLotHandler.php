<?php

namespace App\Billing\Application\Command\ConfirmLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class ConfirmLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ConfirmLotCommand $command): array
    {
        return $this->lotPort->confirmLot($command->lotId);
    }
}

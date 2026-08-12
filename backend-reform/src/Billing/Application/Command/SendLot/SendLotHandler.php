<?php

namespace App\Billing\Application\Command\SendLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class SendLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SendLotCommand $command): array
    {
        return $this->lotPort->sendLot($command->lotId);
    }
}

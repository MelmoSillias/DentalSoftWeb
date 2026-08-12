<?php

namespace App\Billing\Application\Command\ReopenLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class ReopenLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ReopenLotCommand $command): array
    {
        return $this->lotPort->reopenLot($command->lotId);
    }
}

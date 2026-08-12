<?php

namespace App\Billing\Application\Command\UnconfirmLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class UnconfirmLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UnconfirmLotCommand $command): array
    {
        return $this->lotPort->unconfirmLot($command->lotId);
    }
}

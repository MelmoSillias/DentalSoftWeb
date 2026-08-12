<?php

namespace App\Billing\Application\Command\RefundLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class RefundLotHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RefundLotCommand $command): array
    {
        return $this->lotPort->refundLot(
            $command->lotId,
            $command->modeId,
            $command->amount,
            $command->date,
        );
    }
}

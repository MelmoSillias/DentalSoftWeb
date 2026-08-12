<?php

namespace App\Billing\Application\Command\CancelRefund;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\CommandHandler;

final class CancelRefundHandler implements CommandHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CancelRefundCommand $command): array
    {
        return $this->lotPort->cancelRefund(
            $command->lotId,
            $command->transactionId,
            $command->comment,
        );
    }
}

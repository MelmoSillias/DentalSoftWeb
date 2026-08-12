<?php

namespace App\Billing\Application\Command\DeleteFixedCharge;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class DeleteFixedChargeHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeleteFixedChargeCommand $command): array
    {
        return $this->financeWrite->deleteFixedCharge($command->id);
    }
}

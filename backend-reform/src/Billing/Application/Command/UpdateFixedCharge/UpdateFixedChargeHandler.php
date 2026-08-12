<?php

namespace App\Billing\Application\Command\UpdateFixedCharge;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateFixedChargeHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateFixedChargeCommand $command): array
    {
        return $this->financeWrite->updateFixedCharge($command->id, $command->data);
    }
}

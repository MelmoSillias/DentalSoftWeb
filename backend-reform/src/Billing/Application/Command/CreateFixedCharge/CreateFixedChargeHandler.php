<?php

namespace App\Billing\Application\Command\CreateFixedCharge;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateFixedChargeHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateFixedChargeCommand $command): array
    {
        return $this->financeWrite->createFixedCharge($command->data);
    }
}

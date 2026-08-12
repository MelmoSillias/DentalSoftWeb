<?php

namespace App\Billing\Application\Command\TogglePaymentMethod;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class TogglePaymentMethodHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(TogglePaymentMethodCommand $command): array
    {
        return $this->financeWrite->togglePaymentMethod($command->id);
    }
}

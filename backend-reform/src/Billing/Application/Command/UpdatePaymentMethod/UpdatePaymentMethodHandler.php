<?php

namespace App\Billing\Application\Command\UpdatePaymentMethod;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdatePaymentMethodHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdatePaymentMethodCommand $command): array
    {
        return $this->financeWrite->updatePaymentMethod($command->id, $command->data);
    }
}

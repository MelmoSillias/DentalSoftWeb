<?php

namespace App\Billing\Application\Command\DeletePaymentMethod;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class DeletePaymentMethodHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeletePaymentMethodCommand $command): array
    {
        return $this->financeWrite->deletePaymentMethod($command->id);
    }
}

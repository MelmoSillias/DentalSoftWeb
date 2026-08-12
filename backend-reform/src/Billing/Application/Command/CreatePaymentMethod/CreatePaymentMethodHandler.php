<?php

namespace App\Billing\Application\Command\CreatePaymentMethod;

use App\Billing\Application\Port\FinanceWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreatePaymentMethodHandler implements CommandHandler
{
    public function __construct(private readonly FinanceWritePort $financeWrite)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePaymentMethodCommand $command): array
    {
        return $this->financeWrite->createPaymentMethod($command->data);
    }
}

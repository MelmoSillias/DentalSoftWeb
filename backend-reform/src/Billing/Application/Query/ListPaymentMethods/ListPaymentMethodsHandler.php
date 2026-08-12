<?php

namespace App\Billing\Application\Query\ListPaymentMethods;

use App\Billing\Application\Port\FinanceReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListPaymentMethodsHandler implements QueryHandler
{
    public function __construct(private readonly FinanceReadPort $financeRead)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListPaymentMethodsQuery $query): array
    {
        return $this->financeRead->listPaymentMethods();
    }
}

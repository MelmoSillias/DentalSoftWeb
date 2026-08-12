<?php

namespace App\Billing\Application\Query\GetTransactions;

use App\Billing\Application\Port\BillingReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetTransactionsHandler implements QueryHandler
{
    public function __construct(private readonly BillingReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(GetTransactionsQuery $query): array
    {
        return $this->readPort->getTransactionsByDateRange($query->startDate, $query->endDate);
    }
}

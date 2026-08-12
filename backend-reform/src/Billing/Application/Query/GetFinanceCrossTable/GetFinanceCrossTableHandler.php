<?php

namespace App\Billing\Application\Query\GetFinanceCrossTable;

use App\Billing\Application\Port\FinanceReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFinanceCrossTableHandler implements QueryHandler
{
    public function __construct(private readonly FinanceReadPort $financeRead)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetFinanceCrossTableQuery $query): array
    {
        return $this->financeRead->getMonthlyCrossTable($query->year, $query->month, $query->type);
    }
}

<?php

namespace App\Billing\Application\Query\GetFinanceChartData;

use App\Billing\Application\Port\FinanceReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFinanceChartDataHandler implements QueryHandler
{
    public function __construct(private readonly FinanceReadPort $financeRead)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetFinanceChartDataQuery $query): array
    {
        return $this->financeRead->getChartData($query->year);
    }
}

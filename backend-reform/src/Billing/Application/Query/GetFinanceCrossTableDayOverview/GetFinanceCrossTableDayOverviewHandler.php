<?php

namespace App\Billing\Application\Query\GetFinanceCrossTableDayOverview;

use App\Billing\Application\Port\FinanceReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFinanceCrossTableDayOverviewHandler implements QueryHandler
{
    public function __construct(private readonly FinanceReadPort $financeRead)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetFinanceCrossTableDayOverviewQuery $query): array
    {
        return $this->financeRead->getCrossTableDayOverview($query->date);
    }
}

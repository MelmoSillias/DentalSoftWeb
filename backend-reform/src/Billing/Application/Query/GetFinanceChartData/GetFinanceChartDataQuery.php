<?php

namespace App\Billing\Application\Query\GetFinanceChartData;

final class GetFinanceChartDataQuery
{
    public function __construct(public readonly int $year)
    {
    }
}

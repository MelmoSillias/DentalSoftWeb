<?php

namespace App\Billing\Application\Query\GetFinanceCrossTableDayOverview;

final class GetFinanceCrossTableDayOverviewQuery
{
    public function __construct(public readonly string $date)
    {
    }
}

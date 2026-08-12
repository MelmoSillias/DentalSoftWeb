<?php

namespace App\Billing\Application\Query\ListFixedCharges;

use App\Billing\Application\Port\FinanceReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListFixedChargesHandler implements QueryHandler
{
    public function __construct(private readonly FinanceReadPort $financeRead)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: float}
     */
    public function __invoke(ListFixedChargesQuery $query): array
    {
        return $this->financeRead->listFixedCharges();
    }
}

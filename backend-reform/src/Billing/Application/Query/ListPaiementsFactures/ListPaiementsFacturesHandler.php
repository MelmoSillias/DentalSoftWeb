<?php

namespace App\Billing\Application\Query\ListPaiementsFactures;

use App\Billing\Application\Port\BillingReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListPaiementsFacturesHandler implements QueryHandler
{
    public function __construct(private readonly BillingReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListPaiementsFacturesQuery $query): array
    {
        return $this->readPort->listPaiementsFactures($query->start, $query->end);
    }
}

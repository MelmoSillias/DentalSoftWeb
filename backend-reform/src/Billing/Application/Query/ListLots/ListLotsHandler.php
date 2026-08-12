<?php

namespace App\Billing\Application\Query\ListLots;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\QueryHandler;

final class ListLotsHandler implements QueryHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListLotsQuery $query): array
    {
        return $this->lotPort->listLots($query->assuranceCode, $query->statut);
    }
}

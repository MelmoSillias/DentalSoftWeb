<?php

namespace App\Billing\Application\Query\GetAssuranceDashboard;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\QueryHandler;

final class GetAssuranceDashboardHandler implements QueryHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(GetAssuranceDashboardQuery $query): array
    {
        return $this->lotPort->getDashboard();
    }
}

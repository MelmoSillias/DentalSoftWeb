<?php

namespace App\Billing\Application\Query\ListFacturesAssurance;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListFacturesAssuranceHandler implements QueryHandler
{
    public function __construct(private readonly InsuredBillingPort $insuredBilling)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListFacturesAssuranceQuery $query): array
    {
        return $this->insuredBilling->listFacturesAssurance(
            $query->start,
            $query->end,
            $query->status,
            $query->patientQuery,
            $query->assuranceCode,
        );
    }
}

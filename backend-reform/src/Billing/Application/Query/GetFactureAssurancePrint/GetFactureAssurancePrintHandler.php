<?php

namespace App\Billing\Application\Query\GetFactureAssurancePrint;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFactureAssurancePrintHandler implements QueryHandler
{
    public function __construct(private readonly InsuredBillingPort $insuredBilling)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetFactureAssurancePrintQuery $query): ?array
    {
        return $this->insuredBilling->mapFactureAssurancePrint($query->factureId);
    }
}

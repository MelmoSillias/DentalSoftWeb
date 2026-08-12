<?php

namespace App\Billing\Application\Query\GetClaimDetail;

use App\Billing\Application\Port\InsuredBillingPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetClaimDetailHandler implements QueryHandler
{
    public function __construct(private readonly InsuredBillingPort $insuredBilling)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetClaimDetailQuery $query): array
    {
        return $this->insuredBilling->getClaimDetail($query->factureId);
    }
}

<?php

namespace App\Billing\Application\Query\GetLot;

use App\Billing\Application\Port\LotFactureAssurancePort;
use App\Shared\Application\Bus\QueryHandler;

final class GetLotHandler implements QueryHandler
{
    public function __construct(private readonly LotFactureAssurancePort $lotPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetLotQuery $query): array
    {
        return $this->lotPort->getLot($query->lotId);
    }
}

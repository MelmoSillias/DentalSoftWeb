<?php

namespace App\CareDelivery\Application\Query\GetOrdonnance;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetOrdonnanceHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetOrdonnanceQuery $query): ?array
    {
        return $this->readPort->getOrdonnanceData($query->ordonnanceId);
    }
}

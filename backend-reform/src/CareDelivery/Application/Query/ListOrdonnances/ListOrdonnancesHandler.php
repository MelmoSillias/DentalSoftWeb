<?php

namespace App\CareDelivery\Application\Query\ListOrdonnances;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListOrdonnancesHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListOrdonnancesQuery $query): array
    {
        return $this->readPort->listOrdonnances($query->consultationId);
    }
}

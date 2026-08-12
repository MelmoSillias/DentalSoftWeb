<?php

namespace App\CareDelivery\Application\Query\GetFactureLines;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetFactureLinesHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetFactureLinesQuery $query): ?array
    {
        return $this->readPort->getFactureLines($query->consultationId);
    }
}

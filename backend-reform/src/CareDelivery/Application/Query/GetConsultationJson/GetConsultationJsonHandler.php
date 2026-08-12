<?php

namespace App\CareDelivery\Application\Query\GetConsultationJson;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class GetConsultationJsonHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetConsultationJsonQuery $query): array
    {
        return $this->readPort->getFicheConsultationJson(
            $query->ficheId,
            $query->consultationId,
            $query->user,
            $query->restrictToMedecin,
        );
    }
}

<?php

namespace App\CareDelivery\Application\Query\GetConsultationDetails;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;
use InvalidArgumentException;

final class GetConsultationDetailsHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetConsultationDetailsQuery $query): array
    {
        return match ($query->mode) {
            GetConsultationDetailsQuery::MODE_CONTEXT => $this->readPort->getConsultationDetailsContext($query->consultationId),
            GetConsultationDetailsQuery::MODE_DATA => $this->readPort->getConsultationDetailsData($query->consultationId),
            default => throw new InvalidArgumentException(sprintf('Unsupported consultation details mode "%s".', $query->mode)),
        };
    }
}

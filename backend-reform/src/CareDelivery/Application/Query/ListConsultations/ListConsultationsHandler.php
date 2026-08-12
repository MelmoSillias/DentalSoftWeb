<?php

namespace App\CareDelivery\Application\Query\ListConsultations;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\Shared\Application\Bus\QueryHandler;
use InvalidArgumentException;

final class ListConsultationsHandler implements QueryHandler
{
    public function __construct(private readonly ConsultationReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function __invoke(ListConsultationsQuery $query): array
    {
        return match ($query->scope) {
            ListConsultationsQuery::SCOPE_PENDING => $this->readPort->listPendingConsultationsJsonForUser(
                $query->user,
                $query->restrictToMedecin,
            ),
            ListConsultationsQuery::SCOPE_CLOSED => $this->readPort->getClosedConsultationsData(),
            ListConsultationsQuery::SCOPE_DAY => $this->readPort->consultationsDuJour($query->date, $query->user),
            ListConsultationsQuery::SCOPE_RECEPTION_FOCUS => $this->readPort->getReceptionFocusData($query->date, $query->user),
            default => throw new InvalidArgumentException(sprintf('Unsupported consultations list scope "%s".', $query->scope)),
        };
    }
}

<?php

namespace App\Patient\Application\Query\CheckActiveConsultation;

use App\Patient\Application\Port\CheckActiveConsultationPort;
use App\Shared\Application\Bus\QueryHandler;

final class CheckActiveConsultationHandler implements QueryHandler
{
    public function __construct(private readonly CheckActiveConsultationPort $checkActiveConsultationPort)
    {
    }

    /**
     * @return array{hasActive: bool, consultationId: int|null, hasFiche: bool}
     */
    public function __invoke(CheckActiveConsultationQuery $query): array
    {
        return $this->checkActiveConsultationPort->checkActive($query->patientId);
    }
}

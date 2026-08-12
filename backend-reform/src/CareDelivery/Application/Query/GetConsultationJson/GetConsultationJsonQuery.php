<?php

namespace App\CareDelivery\Application\Query\GetConsultationJson;

final class GetConsultationJsonQuery
{
    public function __construct(
        public readonly int $ficheId,
        public readonly int $consultationId,
        public readonly ?object $user = null,
        public readonly bool $restrictToMedecin = false,
    ) {
    }
}

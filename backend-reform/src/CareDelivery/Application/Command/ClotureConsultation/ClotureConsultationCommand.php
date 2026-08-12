<?php

namespace App\CareDelivery\Application\Command\ClotureConsultation;

final class ClotureConsultationCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly int $consultationId,
        public readonly ?object $user = null,
        public readonly bool $restrictToMedecin = false,
        public readonly array $payload = [],
    ) {
    }
}

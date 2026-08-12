<?php

namespace App\CareDelivery\Application\Command\UpdateConsultation;

final class UpdateConsultationCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly int $consultationId,
        public readonly array $data,
        public readonly ?object $user = null,
        public readonly bool $restrictToMedecin = false,
    ) {
    }
}

<?php

namespace App\Patient\Application\Command\CreatePatientConsultation;

final class CreatePatientConsultationCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $patientId,
        public readonly array $payload,
        public readonly ?object $actor = null,
    ) {
    }
}

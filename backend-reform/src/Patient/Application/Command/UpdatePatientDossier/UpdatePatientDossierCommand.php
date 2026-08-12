<?php

namespace App\Patient\Application\Command\UpdatePatientDossier;

final class UpdatePatientDossierCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $patientId,
        public readonly array $payload,
    ) {
    }
}

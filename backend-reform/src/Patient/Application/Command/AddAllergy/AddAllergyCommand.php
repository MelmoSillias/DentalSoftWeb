<?php

namespace App\Patient\Application\Command\AddAllergy;

final class AddAllergyCommand
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

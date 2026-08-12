<?php

namespace App\Patient\Application\Command\AddAntecedent;

final class AddAntecedentCommand
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

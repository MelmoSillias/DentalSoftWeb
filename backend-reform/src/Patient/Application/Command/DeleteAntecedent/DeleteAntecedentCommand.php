<?php

namespace App\Patient\Application\Command\DeleteAntecedent;

final class DeleteAntecedentCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $antecedentId,
    ) {
    }
}

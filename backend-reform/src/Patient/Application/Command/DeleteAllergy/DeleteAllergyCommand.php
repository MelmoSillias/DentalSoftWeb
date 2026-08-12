<?php

namespace App\Patient\Application\Command\DeleteAllergy;

final class DeleteAllergyCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $allergyId,
    ) {
    }
}

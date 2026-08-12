<?php

namespace App\Patient\Application\Command\RestorePatient;

final class RestorePatientCommand
{
    public function __construct(
        public readonly int $patientId,
    ) {
    }
}

<?php

namespace App\Patient\Application\Command\SoftDeletePatient;

final class SoftDeletePatientCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly ?int $actorUserId = null,
    ) {
    }
}

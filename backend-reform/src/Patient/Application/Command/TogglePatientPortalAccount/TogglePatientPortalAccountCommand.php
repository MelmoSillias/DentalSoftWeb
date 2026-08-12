<?php

namespace App\Patient\Application\Command\TogglePatientPortalAccount;

final class TogglePatientPortalAccountCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly bool $active,
    ) {
    }
}

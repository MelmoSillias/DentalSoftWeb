<?php

namespace App\Patient\Application\Command\CreatePatientPortalAccount;

final class CreatePatientPortalAccountCommand
{
    public function __construct(public readonly int $patientId)
    {
    }
}

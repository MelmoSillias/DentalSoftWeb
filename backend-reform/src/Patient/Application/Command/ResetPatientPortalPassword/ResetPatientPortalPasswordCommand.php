<?php

namespace App\Patient\Application\Command\ResetPatientPortalPassword;

final class ResetPatientPortalPasswordCommand
{
    public function __construct(public readonly int $patientId)
    {
    }
}

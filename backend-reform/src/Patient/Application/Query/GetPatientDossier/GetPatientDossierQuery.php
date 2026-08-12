<?php

namespace App\Patient\Application\Query\GetPatientDossier;

final class GetPatientDossierQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

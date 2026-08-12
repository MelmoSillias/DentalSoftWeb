<?php

namespace App\Patient\Application\Query\ListPatientConsultations;

final class ListPatientConsultationsQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

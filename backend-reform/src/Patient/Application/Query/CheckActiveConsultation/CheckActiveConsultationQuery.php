<?php

namespace App\Patient\Application\Query\CheckActiveConsultation;

final class CheckActiveConsultationQuery
{
    public function __construct(public readonly int $patientId)
    {
    }
}

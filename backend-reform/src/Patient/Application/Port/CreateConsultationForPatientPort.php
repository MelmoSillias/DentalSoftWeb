<?php

namespace App\Patient\Application\Port;

interface CreateConsultationForPatientPort
{
    /**
     * Creates a consultation for a patient (legacy createConsultation payload).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?object $actor = null): array;
}

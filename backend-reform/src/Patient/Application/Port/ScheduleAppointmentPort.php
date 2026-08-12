<?php

namespace App\Patient\Application\Port;

interface ScheduleAppointmentPort
{
    /**
     * Schedules an RDV for a patient (legacy createRdv payload).
     * Implemented by Scheduling (`RdvService::createRdvFromPatientPayload`) — orchestration does not live in PatientService.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function schedule(array $data, ?object $actor = null): array;
}

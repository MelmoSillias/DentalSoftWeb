<?php

namespace App\Patient\Application\Port;

interface CloseActiveConsultationsPort
{
    public function closeActiveConsultations(int $patientId, ?int $actorUserId): void;
}

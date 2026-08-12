<?php

namespace App\Patient\Application\Port;

interface CheckActiveConsultationPort
{
    /**
     * @return array{hasActive: bool, consultationId: int|null, hasFiche: bool}
     */
    public function checkActive(int $patientId): array;
}

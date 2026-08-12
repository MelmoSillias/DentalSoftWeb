<?php

namespace App\Patient\Application\Port;

interface PatientCachePort
{
    public function clearPatientsCache(): void;
}

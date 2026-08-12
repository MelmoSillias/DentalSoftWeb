<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationPatientPort
{
    public function findPatient(int $id): ?object;

    public function hasActiveInsurance(object $patient): bool;

    public function getInsuranceProfile(object $patient): ?array;
}

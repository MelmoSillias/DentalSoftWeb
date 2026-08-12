<?php

namespace App\CareDelivery\Application\Port;

interface ConsultationStaffPort
{
    public function findEmployeForUser(?object $user): ?object;

    public function verifyUserPassword(object $user, string $plain): bool;

    public function listMedecins(): array;

    public function listInfirmiers(): array;

    public function invalidateStaffReferenceCache(): void;
}

<?php

namespace App\Patient\Application\Port;

interface PatientCreatedSideEffectsPort
{
    /**
     * Runs notify / focus-adjacent SMS / portal auto-create after patient creation.
     *
     * @return array{id: int|null, username: string|null, active: bool, roles: list<string>, defaultPassword: string}|null
     */
    public function afterCreate(int $patientId, ?int $actorUserId): ?array;
}

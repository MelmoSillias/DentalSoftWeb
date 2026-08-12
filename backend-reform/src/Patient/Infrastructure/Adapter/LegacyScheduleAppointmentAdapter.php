<?php

namespace App\Patient\Infrastructure\Adapter;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Application\Port\ScheduleAppointmentPort;
use App\Scheduling\Service\RdvService;

/**
 * Patient → Scheduling bridge: RDV creation orchestration lives in Scheduling (RdvService).
 */
final class LegacyScheduleAppointmentAdapter implements ScheduleAppointmentPort
{
    public function __construct(private readonly RdvService $rdvService)
    {
    }

    public function schedule(array $data, ?object $actor = null): array
    {
        $user = $actor instanceof User ? $actor : null;

        return $this->rdvService->createRdvFromPatientPayload($data, $user);
    }
}

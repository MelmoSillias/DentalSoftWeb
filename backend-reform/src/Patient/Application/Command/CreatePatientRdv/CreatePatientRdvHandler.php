<?php

namespace App\Patient\Application\Command\CreatePatientRdv;

use App\Patient\Application\Port\ScheduleAppointmentPort;
use App\Shared\Application\Bus\CommandHandler;

final class CreatePatientRdvHandler implements CommandHandler
{
    public function __construct(private readonly ScheduleAppointmentPort $scheduleAppointmentPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePatientRdvCommand $command): array
    {
        return $this->scheduleAppointmentPort->schedule($command->data, $command->actor);
    }
}

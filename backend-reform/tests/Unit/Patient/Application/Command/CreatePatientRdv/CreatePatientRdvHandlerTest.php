<?php

namespace App\Tests\Unit\Patient\Application\Command\CreatePatientRdv;

use App\Patient\Application\Command\CreatePatientRdv\CreatePatientRdvCommand;
use App\Patient\Application\Command\CreatePatientRdv\CreatePatientRdvHandler;
use App\Patient\Application\Port\ScheduleAppointmentPort;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CreatePatientRdvHandlerTest extends TestCase
{
    public function testDelegatesToScheduleAppointmentPort(): void
    {
        $data = [
            'patient_id' => 10,
            'medecin_id' => 3,
            'date' => '2026-08-10',
            'time' => '09:30',
        ];
        $actor = new stdClass();
        $expected = [
            'success' => true,
            'status' => 201,
            'rdv_id' => 55,
            'smsQueuedCount' => 2,
        ];

        $port = $this->createMock(ScheduleAppointmentPort::class);
        $port->expects(self::once())
            ->method('schedule')
            ->with($data, $actor)
            ->willReturn($expected);

        $handler = new CreatePatientRdvHandler($port);
        $result = $handler(new CreatePatientRdvCommand($data, $actor));

        self::assertSame($expected, $result);
    }
}

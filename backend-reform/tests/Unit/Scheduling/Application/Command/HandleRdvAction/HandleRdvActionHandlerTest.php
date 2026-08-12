<?php

namespace App\Tests\Unit\Scheduling\Application\Command\HandleRdvAction;

use App\Scheduling\Application\Command\HandleRdvAction\HandleRdvActionCommand;
use App\Scheduling\Application\Command\HandleRdvAction\HandleRdvActionHandler;
use App\Scheduling\Application\Port\RdvWritePort;
use PHPUnit\Framework\TestCase;
use stdClass;

final class HandleRdvActionHandlerTest extends TestCase
{
    public function testDelegatesValidateToWritePort(): void
    {
        $payload = ['create_consultation' => true, 'medecin' => 3];
        $actor = new stdClass();
        $expected = ['success' => true];

        $port = $this->createMock(RdvWritePort::class);
        $port->expects(self::once())
            ->method('handleAction')
            ->with(42, 'validate', $payload, $actor)
            ->willReturn($expected);

        $handler = new HandleRdvActionHandler($port);
        $result = $handler(new HandleRdvActionCommand(42, 'validate', $payload, $actor));

        self::assertSame($expected, $result);
    }

    public function testDelegatesCancelToWritePort(): void
    {
        $port = $this->createMock(RdvWritePort::class);
        $port->expects(self::once())
            ->method('handleAction')
            ->with(7, 'cancel', [], null)
            ->willReturn(['success' => true]);

        $handler = new HandleRdvActionHandler($port);
        $result = $handler(new HandleRdvActionCommand(7, 'cancel'));

        self::assertSame(['success' => true], $result);
    }

    public function testDelegatesReportToWritePort(): void
    {
        $payload = [
            'new_date' => '2026-08-10',
            'new_time' => '14:00',
            'new_duration' => 30,
        ];

        $port = $this->createMock(RdvWritePort::class);
        $port->expects(self::once())
            ->method('handleAction')
            ->with(11, 'report', $payload, null)
            ->willReturn(['success' => true]);

        $handler = new HandleRdvActionHandler($port);
        $result = $handler(new HandleRdvActionCommand(11, 'report', $payload));

        self::assertSame(['success' => true], $result);
    }
}

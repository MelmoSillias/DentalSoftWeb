<?php

namespace App\Tests\Unit\IdentityAccess\Application\Command\CreateEmployee;

use App\IdentityAccess\Application\Command\CreateEmployee\CreateEmployeeCommand;
use App\IdentityAccess\Application\Command\CreateEmployee\CreateEmployeeHandler;
use App\IdentityAccess\Application\Port\EmployeeWritePort;
use PHPUnit\Framework\TestCase;

final class CreateEmployeeHandlerTest extends TestCase
{
    public function testDelegatesToWritePort(): void
    {
        $data = ['nom' => 'Doe', 'prenom' => 'Jane'];
        $expected = ['message' => 'ok', 'id' => 11];

        $port = $this->createMock(EmployeeWritePort::class);
        $port->expects(self::once())
            ->method('createEmployee')
            ->with($data, [])
            ->willReturn($expected);

        $handler = new CreateEmployeeHandler($port);
        $result = $handler(new CreateEmployeeCommand($data));

        self::assertSame($expected, $result);
    }
}

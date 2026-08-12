<?php

namespace App\Tests\Unit\IdentityAccess\Application\Query\ListEmployees;

use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\IdentityAccess\Application\Query\ListEmployees\ListEmployeesHandler;
use App\IdentityAccess\Application\Query\ListEmployees\ListEmployeesQuery;
use PHPUnit\Framework\TestCase;

final class ListEmployeesHandlerTest extends TestCase
{
    public function testDelegatesToReadPort(): void
    {
        $expected = [
            'data' => [['id' => 1, 'nom' => 'Doe']],
            'total' => 1,
            'filtered' => 1,
        ];

        $port = $this->createMock(EmployeeReadPort::class);
        $port->expects(self::once())
            ->method('listEmployeesPaginated')
            ->with(0, 10, 'doe')
            ->willReturn($expected);

        $handler = new ListEmployeesHandler($port);
        $result = $handler(new ListEmployeesQuery(0, 10, 'doe'));

        self::assertSame($expected, $result);
    }
}

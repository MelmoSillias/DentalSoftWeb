<?php

namespace App\Tests\Unit\Patient\Application\Query\SearchPatients;

use App\Patient\Application\Port\PatientReadPort;
use App\Patient\Application\Query\SearchPatients\SearchPatientsHandler;
use App\Patient\Application\Query\SearchPatients\SearchPatientsQuery;
use PHPUnit\Framework\TestCase;

final class SearchPatientsHandlerTest extends TestCase
{
    public function testDelegatesToReadPortAndReturnsResults(): void
    {
        $expected = [
            [
                'id' => 1,
                'nom' => 'Doe',
                'prenom' => 'Jane',
                'fullname' => 'Jane Doe',
                'telephone' => '770000000',
            ],
        ];

        $readPort = $this->createMock(PatientReadPort::class);
        $readPort->expects(self::once())
            ->method('searchPatients')
            ->with('jane', 15)
            ->willReturn($expected);

        $handler = new SearchPatientsHandler($readPort);
        $result = $handler(new SearchPatientsQuery('jane', 15));

        self::assertSame($expected, $result);
    }

    public function testUsesDefaultLimit(): void
    {
        $readPort = $this->createMock(PatientReadPort::class);
        $readPort->expects(self::once())
            ->method('searchPatients')
            ->with('doe', 20)
            ->willReturn([]);

        $handler = new SearchPatientsHandler($readPort);
        $result = $handler(new SearchPatientsQuery('doe'));

        self::assertSame([], $result);
    }
}

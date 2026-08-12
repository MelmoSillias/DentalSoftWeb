<?php

namespace App\Tests\Unit\Patient\Application\Query\ListPatientConsultations;

use App\Patient\Application\Port\PatientConsultationsReadPort;
use App\Patient\Application\Query\ListPatientConsultations\ListPatientConsultationsHandler;
use App\Patient\Application\Query\ListPatientConsultations\ListPatientConsultationsQuery;
use PHPUnit\Framework\TestCase;

final class ListPatientConsultationsHandlerTest extends TestCase
{
    public function testDelegatesToConsultationsReadPort(): void
    {
        $expected = [
            [
                'id' => 10,
                'date' => '2026-08-01 10:00',
                'statut' => 'terminee',
                'medecin' => 'Dr Test',
                'factureMontant' => 5000,
                'factureStatut' => 1,
            ],
        ];

        $port = $this->createMock(PatientConsultationsReadPort::class);
        $port->expects(self::once())
            ->method('listPatientConsultations')
            ->with(42)
            ->willReturn($expected);

        $handler = new ListPatientConsultationsHandler($port);
        $result = $handler(new ListPatientConsultationsQuery(42));

        self::assertSame($expected, $result);
    }
}

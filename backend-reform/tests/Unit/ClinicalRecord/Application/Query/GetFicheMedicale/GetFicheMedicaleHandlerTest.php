<?php

namespace App\Tests\Unit\ClinicalRecord\Application\Query\GetFicheMedicale;

use App\ClinicalRecord\Application\Port\FicheMedicaleReadPort;
use App\ClinicalRecord\Application\Query\GetFicheMedicale\GetFicheMedicaleHandler;
use App\ClinicalRecord\Application\Query\GetFicheMedicale\GetFicheMedicaleQuery;
use PHPUnit\Framework\TestCase;

final class GetFicheMedicaleHandlerTest extends TestCase
{
    public function testDelegatesToReadPortAndReturnsJson(): void
    {
        $expected = [
            'id' => 42,
            'patientId' => 7,
            'entretien' => ['motifConsultation' => 'Douleur'],
        ];

        $readPort = $this->createMock(FicheMedicaleReadPort::class);
        $readPort->expects(self::once())
            ->method('getFicheJson')
            ->with(42)
            ->willReturn($expected);

        $handler = new GetFicheMedicaleHandler($readPort);
        $result = $handler(new GetFicheMedicaleQuery(42));

        self::assertSame($expected, $result);
    }
}

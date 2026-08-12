<?php

namespace App\Tests\Unit\CareDelivery\Application\Command\UpdateOrdonnance;

use App\CareDelivery\Application\Command\UpdateOrdonnance\UpdateOrdonnanceCommand;
use App\CareDelivery\Application\Command\UpdateOrdonnance\UpdateOrdonnanceHandler;
use App\CareDelivery\Application\Port\ConsultationWritePort;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateOrdonnanceHandlerTest extends TestCase
{
    public function testValidatesDomainThenDelegatesToWritePort(): void
    {
        $payload = [
            'consultationId' => 10,
            'lignes' => [
                [
                    'designation' => 'Amoxicilline',
                    'posologie' => '1g',
                    'quantite' => 2,
                ],
            ],
        ];
        $expected = ['id' => 3, 'lignes' => $payload['lignes']];

        $writePort = $this->createMock(ConsultationWritePort::class);
        $writePort->expects(self::once())
            ->method('updateOrdonnance')
            ->with(3, $payload)
            ->willReturn($expected);

        $handler = new UpdateOrdonnanceHandler($writePort);
        $result = $handler(new UpdateOrdonnanceCommand(3, $payload));

        self::assertSame($expected, $result);
    }

    public function testRejectsEmptyLinesBeforePort(): void
    {
        $writePort = $this->createMock(ConsultationWritePort::class);
        $writePort->expects(self::never())->method('updateOrdonnance');

        $handler = new UpdateOrdonnanceHandler($writePort);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Au moins une ligne de prescription est requise.');

        $handler(new UpdateOrdonnanceCommand(3, ['lignes' => []]));
    }

    public function testRejectsEmptyDesignationBeforePort(): void
    {
        $writePort = $this->createMock(ConsultationWritePort::class);
        $writePort->expects(self::never())->method('updateOrdonnance');

        $handler = new UpdateOrdonnanceHandler($writePort);

        $this->expectException(InvalidArgumentException::class);

        $handler(new UpdateOrdonnanceCommand(3, [
            'lignes' => [['designation' => '']],
        ]));
    }
}

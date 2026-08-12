<?php

namespace App\Tests\Unit\Billing\Application\Command\SendLot;

use App\Billing\Application\Command\SendLot\SendLotCommand;
use App\Billing\Application\Command\SendLot\SendLotHandler;
use App\Billing\Application\Port\LotFactureAssurancePort;
use PHPUnit\Framework\TestCase;

final class SendLotHandlerTest extends TestCase
{
    public function testDelegatesToLotFactureAssurancePort(): void
    {
        $expected = ['success' => true, 'data' => ['id' => 7, 'statut' => 'envoye']];

        $port = $this->createMock(LotFactureAssurancePort::class);
        $port->expects(self::once())
            ->method('sendLot')
            ->with(7)
            ->willReturn($expected);

        $handler = new SendLotHandler($port);
        $result = $handler(new SendLotCommand(7));

        self::assertSame($expected, $result);
    }
}

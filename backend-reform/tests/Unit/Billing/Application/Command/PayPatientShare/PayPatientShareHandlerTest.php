<?php

namespace App\Tests\Unit\Billing\Application\Command\PayPatientShare;

use App\Billing\Application\Command\PayPatientShare\PayPatientShareCommand;
use App\Billing\Application\Command\PayPatientShare\PayPatientShareHandler;
use App\Billing\Application\Port\InsuredBillingPort;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PayPatientShareHandlerTest extends TestCase
{
    public function testDelegatesToInsuredBillingPort(): void
    {
        $date = new DateTimeImmutable('2026-08-07 10:00:00');
        $expected = ['success' => true, 'paiementId' => 42];

        $port = $this->createMock(InsuredBillingPort::class);
        $port->expects(self::once())
            ->method('payPatientShare')
            ->with(15, 3, 2500.0, $date)
            ->willReturn($expected);

        $handler = new PayPatientShareHandler($port);
        $result = $handler(new PayPatientShareCommand(15, 3, 2500.0, $date));

        self::assertSame($expected, $result);
    }
}

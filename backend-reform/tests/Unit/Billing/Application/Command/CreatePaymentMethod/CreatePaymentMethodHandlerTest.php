<?php

namespace App\Tests\Unit\Billing\Application\Command\CreatePaymentMethod;

use App\Billing\Application\Command\CreatePaymentMethod\CreatePaymentMethodCommand;
use App\Billing\Application\Command\CreatePaymentMethod\CreatePaymentMethodHandler;
use App\Billing\Application\Port\FinanceWritePort;
use PHPUnit\Framework\TestCase;

final class CreatePaymentMethodHandlerTest extends TestCase
{
    public function testDelegatesToFinanceWritePort(): void
    {
        $payload = ['libelle' => 'Espèces', 'type' => 'cash'];
        $expected = [
            'id' => 3,
            'libelle' => 'Espèces',
            'type' => 'cash',
            'actif' => true,
            'notes' => null,
            'autoValidate' => true,
        ];

        $port = $this->createMock(FinanceWritePort::class);
        $port->expects(self::once())
            ->method('createPaymentMethod')
            ->with($payload)
            ->willReturn($expected);

        $handler = new CreatePaymentMethodHandler($port);
        $result = $handler(new CreatePaymentMethodCommand($payload));

        self::assertSame($expected, $result);
    }
}

<?php

namespace App\Tests\Unit\Billing\Application\Query\GetTransactions;

use App\Billing\Application\Port\BillingReadPort;
use App\Billing\Application\Query\GetTransactions\GetTransactionsHandler;
use App\Billing\Application\Query\GetTransactions\GetTransactionsQuery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetTransactionsHandlerTest extends TestCase
{
    public function testDelegatesToBillingReadPort(): void
    {
        $start = new DateTimeImmutable('2026-08-01 00:00:00');
        $end = new DateTimeImmutable('2026-08-07 23:59:59');
        $expected = [
            ['id' => 1, 'amount' => 100.0],
        ];

        $readPort = $this->createMock(BillingReadPort::class);
        $readPort->expects(self::once())
            ->method('getTransactionsByDateRange')
            ->with($start, $end)
            ->willReturn($expected);

        $handler = new GetTransactionsHandler($readPort);
        $result = $handler(new GetTransactionsQuery($start, $end));

        self::assertSame($expected, $result);
    }
}

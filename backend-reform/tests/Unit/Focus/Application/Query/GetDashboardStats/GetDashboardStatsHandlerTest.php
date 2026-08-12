<?php

namespace App\Tests\Unit\Focus\Application\Query\GetDashboardStats;

use App\Focus\Application\Port\DashboardReadPort;
use App\Focus\Application\Query\GetDashboardStats\GetDashboardStatsHandler;
use App\Focus\Application\Query\GetDashboardStats\GetDashboardStatsQuery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetDashboardStatsHandlerTest extends TestCase
{
    public function testDelegatesAdminCardsToReadPort(): void
    {
        $from = new DateTimeImmutable('2026-08-01 00:00:00');
        $to = new DateTimeImmutable('2026-08-01 23:59:59');
        $expected = ['cards' => []];

        $port = $this->createMock(DashboardReadPort::class);
        $port->expects(self::once())
            ->method('getAdminCards')
            ->with($from, $to)
            ->willReturn($expected);

        $handler = new GetDashboardStatsHandler($port);
        $result = $handler(new GetDashboardStatsQuery('admin', 'cards', $from, $to));

        self::assertSame($expected, $result);
    }
}

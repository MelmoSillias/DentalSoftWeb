<?php

namespace App\Tests\Unit\Reporting\Application\Query\GetGlobalStats;

use App\Reporting\Application\Port\ReportReadPort;
use App\Reporting\Application\Query\GetGlobalStats\GetGlobalStatsHandler;
use App\Reporting\Application\Query\GetGlobalStats\GetGlobalStatsQuery;
use PHPUnit\Framework\TestCase;

final class GetGlobalStatsHandlerTest extends TestCase
{
    public function testDelegatesToReadPort(): void
    {
        $expected = ['capital' => 1000];

        $port = $this->createMock(ReportReadPort::class);
        $port->expects(self::once())
            ->method('globalStats')
            ->with('2026-01-01', '2026-01-31')
            ->willReturn($expected);

        $handler = new GetGlobalStatsHandler($port);
        $result = $handler(new GetGlobalStatsQuery('2026-01-01', '2026-01-31'));

        self::assertSame($expected, $result);
    }
}

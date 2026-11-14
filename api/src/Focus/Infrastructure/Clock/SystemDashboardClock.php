<?php

declare(strict_types=1);

namespace App\Focus\Infrastructure\Clock;

use App\Focus\Application\Port\DashboardClockInterface;

final class SystemDashboardClock implements DashboardClockInterface
{
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable(); }
}

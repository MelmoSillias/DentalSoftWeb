<?php

declare(strict_types=1);

namespace App\Focus\Application\Port;

interface DashboardClockInterface
{
    public function now(): \DateTimeImmutable;
}

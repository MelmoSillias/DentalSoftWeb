<?php

namespace App\Shared\Infrastructure\Adapter;

use App\Shared\Application\Port\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

<?php

declare(strict_types=1);

namespace App\Focus\Application\Port;

interface DashboardCacheInterface
{
    /** @param callable():array $builder */
    public function remember(string $key, int $ttl, callable $builder): array;
}

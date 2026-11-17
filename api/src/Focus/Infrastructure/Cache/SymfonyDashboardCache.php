<?php

declare(strict_types=1);

namespace App\Focus\Infrastructure\Cache;

use App\Focus\Application\Port\DashboardCacheInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SymfonyDashboardCache implements DashboardCacheInterface
{
    public function __construct(private CacheInterface $cache) {}

    public function remember(string $key, int $ttl, callable $builder): array
    {
        return $this->cache->get($key, static function (ItemInterface $item) use ($ttl,$builder): array {
            $item->expiresAfter($ttl);
            return $builder();
        });
    }
}

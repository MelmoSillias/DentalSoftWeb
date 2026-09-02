<?php

namespace App\Communication\Mercure;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Ouvre un circuit temporaire apres des echecs reseau/serveur Mercure.
 */
final class MercureCircuitBreaker
{
    private const CACHE_KEY_OPEN_UNTIL = 'mercure.publish.circuit_open_until';
    private const CACHE_KEY_DROPPED_COUNT = 'mercure.publish.dropped_count';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cooldownSeconds = 60,
    ) {
    }

    public function isOpen(): bool
    {
        $item = $this->cache->getItem(self::CACHE_KEY_OPEN_UNTIL);
        if (!$item->isHit()) {
            return false;
        }

        $openUntil = (int) $item->get();

        return $openUntil > time();
    }

    public function isAvailable(): bool
    {
        return !$this->isOpen();
    }

    public function recordSuccess(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY_OPEN_UNTIL);
    }

    public function recordFailure(): void
    {
        $cooldown = max(1, $this->cooldownSeconds);
        $openUntil = time() + $cooldown;

        $item = $this->cache->getItem(self::CACHE_KEY_OPEN_UNTIL);
        $item->set($openUntil);
        $item->expiresAfter($cooldown);
        $this->cache->save($item);
    }

    public function recordDropped(): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY_DROPPED_COUNT);
        $count = $item->isHit() ? (int) $item->get() : 0;
        $item->set($count + 1);
        $item->expiresAfter(86400);
        $this->cache->save($item);
    }

    public function droppedCount(): int
    {
        $item = $this->cache->getItem(self::CACHE_KEY_DROPPED_COUNT);

        return $item->isHit() ? (int) $item->get() : 0;
    }

    /**
     * @return array{open: bool, openUntil: int|null, cooldownSeconds: int, droppedCount: int}
     */
    public function status(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY_OPEN_UNTIL);
        $openUntil = $item->isHit() ? (int) $item->get() : null;
        $open = $openUntil !== null && $openUntil > time();

        return [
            'open' => $open,
            'openUntil' => $open ? $openUntil : null,
            'cooldownSeconds' => max(1, $this->cooldownSeconds),
            'droppedCount' => $this->droppedCount(),
        ];
    }
}

<?php

namespace App\Communication\Mercure;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Ouvre un circuit temporaire après un échec de publish Mercure
 * pour éviter une rafale de timeouts HTTP.
 */
final class MercureCircuitBreaker
{
    private const CACHE_KEY = 'mercure.publish.circuit_open_until';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cooldownSeconds = 60,
    ) {
    }

    public function isOpen(): bool
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
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
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    public function recordFailure(): void
    {
        $cooldown = max(1, $this->cooldownSeconds);
        $openUntil = time() + $cooldown;

        $item = $this->cache->getItem(self::CACHE_KEY);
        $item->set($openUntil);
        $item->expiresAfter($cooldown);
        $this->cache->save($item);
    }

    /**
     * @return array{open: bool, openUntil: int|null, cooldownSeconds: int}
     */
    public function status(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        $openUntil = $item->isHit() ? (int) $item->get() : null;
        $open = $openUntil !== null && $openUntil > time();

        return [
            'open' => $open,
            'openUntil' => $open ? $openUntil : null,
            'cooldownSeconds' => max(1, $this->cooldownSeconds),
        ];
    }
}

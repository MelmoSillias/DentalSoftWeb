<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\Entity\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function findByHash(string $hash): ?RefreshToken;
    public function save(RefreshToken $token): void;
    /** Atomically consumes the token; only one concurrent request may succeed. */
    public function consume(RefreshToken $token, \DateTimeImmutable $now): bool;
    public function transactional(callable $operation): mixed;
}

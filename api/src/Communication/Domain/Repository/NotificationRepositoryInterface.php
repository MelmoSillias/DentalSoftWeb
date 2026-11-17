<?php

declare(strict_types=1);

namespace App\Communication\Domain\Repository;

interface NotificationRepositoryInterface
{
    public function findLatestForUser(\App\IdentityAccess\Domain\Entity\User $user, int $limit = 20): array;

    public function findByFilter(\App\IdentityAccess\Domain\Entity\User $user, string $filter = 'all', int $limit = 50): array;

    public function findNewerThan(\App\IdentityAccess\Domain\Entity\User $user, int $lastId): array;

    public function countUnread(\App\IdentityAccess\Domain\Entity\User $user): int;

    public function findUnreadWithLink(\App\IdentityAccess\Domain\Entity\User $user, int $limit = 50): array;

    public function markUnreadMatchingPathAsRead(\App\IdentityAccess\Domain\Entity\User $user, string $path, int $limit = 50): int;

    public function purgeOlderThan(\DateTimeInterface $threshold): int;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

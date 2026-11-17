<?php

declare(strict_types=1);

namespace App\Communication\Domain\Repository;

interface WebPushSubscriptionRepositoryInterface
{
    public function findOneByEndpoint(string $endpoint): ?\App\Communication\Domain\Entity\WebPushSubscription;

    public function countForUser(\App\IdentityAccess\Domain\Entity\User $user): int;

    public function findForUser(\App\IdentityAccess\Domain\Entity\User $user): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

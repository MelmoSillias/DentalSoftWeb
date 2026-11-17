<?php

declare(strict_types=1);

namespace App\Communication\Domain\Repository;

interface SmsProviderConfigRepositoryInterface
{
    public function getMainConfig(): \App\Communication\Domain\Entity\SmsProviderConfig;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

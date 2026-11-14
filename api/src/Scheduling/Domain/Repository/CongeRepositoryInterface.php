<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Repository;

interface CongeRepositoryInterface
{
    public function save(\App\Scheduling\Domain\Entity\Conge $entity, bool $flush = false): void;

    public function remove(\App\Scheduling\Domain\Entity\Conge $entity, bool $flush = false): void;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

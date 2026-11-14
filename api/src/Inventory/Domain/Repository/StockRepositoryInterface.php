<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

interface StockRepositoryInterface
{
    public function findBetweenDates(\DateTimeInterface $start, \DateTimeInterface $end): array;

    /** @return list<\App\Inventory\Domain\Entity\Stock> */
    public function findHistory(\DateTimeInterface $start, \DateTimeInterface $end, ?int $consumableId = null): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

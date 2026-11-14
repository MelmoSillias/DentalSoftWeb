<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

interface DevisRepositoryInterface
{
    public function findByPortalPatient(\App\Patient\Domain\Entity\Patient $patient): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

interface LotFactureAssuranceRepositoryInterface
{
    public function findOpenLotForAssurance(\App\Billing\Domain\Entity\Assurance $assurance): ?\App\Billing\Domain\Entity\LotFactureAssurance;

    public function findOpenLotsForAssurance(\App\Billing\Domain\Entity\Assurance $assurance): array;

    public function findLatestOpenLotForAssurance(\App\Billing\Domain\Entity\Assurance $assurance): ?\App\Billing\Domain\Entity\LotFactureAssurance;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

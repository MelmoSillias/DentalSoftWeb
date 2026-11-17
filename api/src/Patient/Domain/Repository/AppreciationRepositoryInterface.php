<?php

declare(strict_types=1);

namespace App\Patient\Domain\Repository;

interface AppreciationRepositoryInterface
{
    public function findByPatient(\App\Patient\Domain\Entity\Patient $patient): array;

    public function findPublishedByPatient(\App\Patient\Domain\Entity\Patient $patient): array;

    public function findOneByConsultation(\App\CareDelivery\Domain\Entity\Consultation $consultation): ?\App\Patient\Domain\Entity\Appreciation;

    public function findLatestForAdmin(int $limit = 200): array;

    public function getAdminStats(): array;

    public function findPublishedForPublic(int $limit = 50): array;

    public function getPublicStats(): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

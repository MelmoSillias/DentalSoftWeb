<?php

declare(strict_types=1);

namespace App\Patient\Domain\Repository;

interface PatientRepositoryInterface
{
    public function FindAllPatients(): array;

    public function addPatient(array $data): \App\Patient\Domain\Entity\Patient;

    public function findOneByPortalIdentifier(string $identifier): ?\App\Patient\Domain\Entity\Patient;

    public function findPatientById(int $id): ?array;

    public function updatePatient(int $id, array $data): bool;

    public function paginatePatients(int $page, int $limit, ?string $term = null, ?string $sortField = null, ?string $sortOrder = null): array;

    public function paginatePatientsByMedecin(\App\IdentityAccess\Domain\Entity\Employe $medecin, int $page, int $limit, ?string $term = null, ?string $sortField = null, ?string $sortOrder = null): array;

    public function findPatientsByMedecin(\App\IdentityAccess\Domain\Entity\Employe $medecin, ?string $term = null, ?string $sortField = null, ?string $sortOrder = null): array;

    public function paginateDeletedPatients(int $page, int $limit, ?string $term = null): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

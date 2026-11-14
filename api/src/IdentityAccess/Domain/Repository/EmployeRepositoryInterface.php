<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

interface EmployeRepositoryInterface
{
    public function createEmployeeWithOptionalUser(array $data): \App\IdentityAccess\Domain\Entity\Employe;

    public function findID(int $id): ?array;

    public function FindAllMedecin(): ?array;

    public function findAllInfirmiers(): array;

    public function findByTypeInsensitive(string $type): array;

    public function FindEmployeeByid(): ?array;

    public function findAllEmployees(): array;

    public function findAllActive(): array;

    public function findDistinctTypes(): array;

    public function updateEmployee(\App\IdentityAccess\Domain\Entity\Employe $employee): void;

    public function findEmployeesWithPagination(int $start, int $length, string $searchValue): array;

    public function countFiltered(string $searchValue): int;

    public function paginateDeletedEmployees(int $page, int $limit, ?string $term = null): array;

    public function emailExists(string $email, ?int $excludeId = null): bool;

    public function matriculeExists(string $matricule, ?int $excludeId = null): bool;

    public function sumValeurSalaireByType(string $typeSalaire): float;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

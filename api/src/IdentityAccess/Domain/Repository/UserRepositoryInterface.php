<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

interface UserRepositoryInterface
{
    public function loadUserByIdentifier(string $identifier): ?\App\IdentityAccess\Domain\Entity\User;

    public function upgradePassword(\Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;

    public function countByRole(string $role): int;

    public function findByRole(string $role): array;

    public function findByRoles(array $roles): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

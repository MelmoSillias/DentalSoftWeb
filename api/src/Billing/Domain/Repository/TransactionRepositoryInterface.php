<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

interface TransactionRepositoryInterface
{
    public function findByDateRange(?\DateTimeInterface $from, ?\DateTimeInterface $to): array;

    public function findValidatedBetweenByTypes(\DateTimeInterface $from, \DateTimeInterface $to, array $types = []): array;

    public function findByPortalPatient(\App\Patient\Domain\Entity\Patient $patient): array;

    public function findPortalReceiptById(\App\Patient\Domain\Entity\Patient $patient, int $transactionId): ?\App\Billing\Domain\Entity\Transaction;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

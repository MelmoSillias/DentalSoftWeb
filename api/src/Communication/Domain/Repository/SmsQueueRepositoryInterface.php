<?php

declare(strict_types=1);

namespace App\Communication\Domain\Repository;

interface SmsQueueRepositoryInterface
{
    public function findProcessable(int $limit = 20): array;

    public function findAppointmentRemindersForPatients(array $patientIds): array;

    public function findByRdvMetadata(int $rdvId, ?array $statuses = null): array;

    public function findRecentQueue(int $limit = 100, int $offset = 0, ?string $status = null): array;

    public function getProcessingSnapshot(): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

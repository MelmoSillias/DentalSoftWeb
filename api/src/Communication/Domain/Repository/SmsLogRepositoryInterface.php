<?php

declare(strict_types=1);

namespace App\Communication\Domain\Repository;

interface SmsLogRepositoryInterface
{
    public function countSentBetween(\DateTimeInterface $start, \DateTimeInterface $end): int;

    public function countTotalSent(): int;

    public function findRecent(int $limit = 50, int $offset = 0): array;

    public function dailySentSeries(int $days = 7): array;

    public function monthlySentSeries(int $months = 6): array;

    public function findByPhoneSince(string $phone, \DateTimeImmutable $since, int $limit = 50): array;

    public function countByStatusBetween(\DateTimeInterface $start, \DateTimeInterface $end, string $status): int;

    public function countBetween(\DateTimeInterface $start, \DateTimeInterface $end): int;

    public function dailySentSeriesBetween(\DateTimeInterface $start, \DateTimeInterface $end): array;

    public function sentByTypeBetween(\DateTimeInterface $start, \DateTimeInterface $end): array;

    public function findLatestByProviderMessageId(string $providerMessageId, string $provider): ?\App\Communication\Domain\Entity\SmsLog;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

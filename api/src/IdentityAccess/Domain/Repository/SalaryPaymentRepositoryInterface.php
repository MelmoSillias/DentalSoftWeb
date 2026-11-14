<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

interface SalaryPaymentRepositoryInterface
{
    public function findByFiltersPaginated(int $start, int $length, ?int $employeeId, ?int $month, ?int $year): array;

    public function countByFilters(?int $employeeId, ?int $month, ?int $year): int;

    public function findLastPaymentForEmployee(\App\IdentityAccess\Domain\Entity\Employe $employee): ?\App\IdentityAccess\Domain\Entity\SalaryPayment;

    public function findFirstForPeriod(\App\IdentityAccess\Domain\Entity\Employe $employee, int $month, int $year, ?string $day = null): ?\App\IdentityAccess\Domain\Entity\SalaryPayment;

    public function sumPaidForMonthlyPeriod(\App\IdentityAccess\Domain\Entity\Employe $employee, int $month, int $year): float;

    public function sumPaidForWorkedDay(\App\IdentityAccess\Domain\Entity\Employe $employee, \DateTimeInterface $workedDay): float;

    public function sumPaidBeforePayment(\App\IdentityAccess\Domain\Entity\SalaryPayment $payment): float;

    public function forPeriod(\App\IdentityAccess\Domain\Entity\SalaryPayment $payment, bool $includeCancelled = false): array;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}

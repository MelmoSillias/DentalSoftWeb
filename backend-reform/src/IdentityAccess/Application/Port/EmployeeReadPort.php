<?php

namespace App\IdentityAccess\Application\Port;

interface EmployeeReadPort
{
    /**
     * @return array{data: list<array<string, mixed>>, total: int, filtered: int}
     */
    public function listEmployeesPaginated(int $start, int $length, string $searchValue): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getEmployeeDetails(int $employeeId): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listMedecins(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listInfirmiers(): array;
}

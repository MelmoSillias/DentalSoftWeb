<?php

namespace App\Patient\Application\Port;

interface PatientReadPort
{
    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function listPatientsCollection(
        ?object $user = null,
        bool $medecinOnly = false,
        bool $paginated = false,
        int $page = 1,
        int $limit = 10,
        ?string $query = null,
        ?string $sortField = null,
        ?string $sortOrder = null,
    ): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getPatientDetailsData(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function searchPatients(string $term, int $limit = 20): array;

    /**
     * @return array<string, mixed>
     */
    public function getOverviewStats(?object $user = null, bool $medecinOnly = false): array;

    /**
     * @return array<string, mixed>
     */
    public function listDeletedPatientsPaginated(int $page, int $limit, ?string $query = null): array;
}

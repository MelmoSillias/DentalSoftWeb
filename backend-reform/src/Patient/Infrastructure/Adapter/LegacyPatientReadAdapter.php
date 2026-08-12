<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientReadPort;
use App\Patient\Service\PatientService;

final class LegacyPatientReadAdapter implements PatientReadPort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function listPatientsCollection(
        ?object $user = null,
        bool $medecinOnly = false,
        bool $paginated = false,
        int $page = 1,
        int $limit = 10,
        ?string $query = null,
        ?string $sortField = null,
        ?string $sortOrder = null,
    ): array {
        return $this->patientService->listPatientsCollection(
            user: $user,
            medecinOnly: $medecinOnly,
            paginated: $paginated,
            page: $page,
            limit: $limit,
            query: $query,
            sortField: $sortField,
            sortOrder: $sortOrder,
        );
    }

    public function getPatientDetailsData(int $id): ?array
    {
        return $this->patientService->getPatientDetailsData($id);
    }

    public function searchPatients(string $term, int $limit = 20): array
    {
        return $this->patientService->searchPatients($term, $limit);
    }

    public function getOverviewStats(?object $user = null, bool $medecinOnly = false): array
    {
        return $this->patientService->getOverviewStats($user, $medecinOnly);
    }

    public function listDeletedPatientsPaginated(int $page, int $limit, ?string $query = null): array
    {
        return $this->patientService->listDeletedPatientsPaginated($page, $limit, $query);
    }
}

<?php

namespace App\Patient\Application\Query\ListPatientsPaginated;

final class ListPatientsPaginatedQuery
{
    public function __construct(
        public readonly ?object $user = null,
        public readonly bool $medecinOnly = false,
        public readonly bool $paginated = false,
        public readonly int $page = 1,
        public readonly int $limit = 10,
        public readonly ?string $query = null,
        public readonly ?string $sortField = null,
        public readonly ?string $sortOrder = null,
    ) {
    }
}

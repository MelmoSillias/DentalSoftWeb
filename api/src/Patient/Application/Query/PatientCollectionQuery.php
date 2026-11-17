<?php

namespace App\Patient\Application\Query;

final readonly class PatientCollectionQuery
{
    public function __construct(
        public ?object $user = null,
        public bool $medecinOnly = false,
        public bool $paginated = false,
        public int $page = 1,
        public int $limit = 10,
        public ?string $query = null,
        public ?string $sortField = null,
        public ?string $sortOrder = null,
    ) {
    }
}

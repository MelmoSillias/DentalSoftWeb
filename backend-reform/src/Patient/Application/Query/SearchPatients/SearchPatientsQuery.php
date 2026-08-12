<?php

namespace App\Patient\Application\Query\SearchPatients;

final class SearchPatientsQuery
{
    public function __construct(
        public readonly string $term,
        public readonly int $limit = 20,
    ) {
    }
}

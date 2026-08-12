<?php

namespace App\Patient\Application\Query\ListDeletedPatients;

final class ListDeletedPatientsQuery
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 10,
        public readonly ?string $query = null,
    ) {
    }
}

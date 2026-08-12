<?php

namespace App\IdentityAccess\Application\Query\ListEmployees;

final class ListEmployeesQuery
{
    public function __construct(
        public readonly int $start = 0,
        public readonly int $length = 10,
        public readonly string $searchValue = '',
    ) {
    }
}

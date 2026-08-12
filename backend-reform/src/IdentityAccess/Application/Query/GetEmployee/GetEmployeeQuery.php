<?php

namespace App\IdentityAccess\Application\Query\GetEmployee;

final class GetEmployeeQuery
{
    public function __construct(public readonly int $employeeId)
    {
    }
}

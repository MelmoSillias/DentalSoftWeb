<?php

namespace App\IdentityAccess\Application\Command\CreatePayroll;

final class CreatePayrollCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public readonly array $payload)
    {
    }
}

<?php

namespace App\IdentityAccess\Application\Port;

interface PayrollWritePort
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSalaryPayment(array $payload): array;
}

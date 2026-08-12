<?php

namespace App\IdentityAccess\Application\Command\CreatePayroll;

use App\IdentityAccess\Application\Port\PayrollWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreatePayrollHandler implements CommandHandler
{
    public function __construct(private readonly PayrollWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePayrollCommand $command): array
    {
        return $this->writePort->createSalaryPayment($command->payload);
    }
}

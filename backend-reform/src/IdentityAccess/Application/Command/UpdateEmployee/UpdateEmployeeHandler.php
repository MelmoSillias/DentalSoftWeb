<?php

namespace App\IdentityAccess\Application\Command\UpdateEmployee;

use App\IdentityAccess\Application\Port\EmployeeWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateEmployeeHandler implements CommandHandler
{
    public function __construct(private readonly EmployeeWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateEmployeeCommand $command): array
    {
        return $this->writePort->updateEmployee($command->employeeId, $command->data, $command->files);
    }
}

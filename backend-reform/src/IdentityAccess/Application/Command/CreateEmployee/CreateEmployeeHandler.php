<?php

namespace App\IdentityAccess\Application\Command\CreateEmployee;

use App\IdentityAccess\Application\Port\EmployeeWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateEmployeeHandler implements CommandHandler
{
    public function __construct(private readonly EmployeeWritePort $writePort)
    {
    }

    /**
     * @return array{message: string, id: int}
     */
    public function __invoke(CreateEmployeeCommand $command): array
    {
        return $this->writePort->createEmployee($command->data, $command->files);
    }
}

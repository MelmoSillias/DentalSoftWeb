<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\EmployeeWritePort;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use App\IdentityAccess\Service\EmployeeService;
use InvalidArgumentException;

final class LegacyEmployeeWriteAdapter implements EmployeeWritePort
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeRepository $employeRepository,
    ) {
    }

    public function createEmployee(array $data, array $files = []): array
    {
        return $this->employeeService->createEmployee($data, $files);
    }

    public function updateEmployee(int $employeeId, array $data, array $files = []): array
    {
        $employee = $this->employeRepository->find($employeeId);
        if (!$employee instanceof Employe) {
            throw new InvalidArgumentException('Employé introuvable.');
        }

        return $this->employeeService->updateEmployee($employee, $data, $files);
    }
}

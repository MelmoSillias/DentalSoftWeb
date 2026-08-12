<?php

namespace App\IdentityAccess\Application\Port;

interface EmployeeWritePort
{
    /**
     * @param array<string, mixed> $data
     * @param list<\Symfony\Component\HttpFoundation\File\UploadedFile> $files
     * @return array{message: string, id: int}
     */
    public function createEmployee(array $data, array $files = []): array;

    /**
     * @param array<string, mixed> $data
     * @param list<\Symfony\Component\HttpFoundation\File\UploadedFile> $files
     * @return array<string, mixed>
     */
    public function updateEmployee(int $employeeId, array $data, array $files = []): array;
}

<?php

namespace App\IdentityAccess\Application\Command\CreateEmployee;

final class CreateEmployeeCommand
{
    /**
     * @param array<string, mixed> $data
     * @param list<\Symfony\Component\HttpFoundation\File\UploadedFile> $files
     */
    public function __construct(
        public readonly array $data,
        public readonly array $files = [],
    ) {
    }
}

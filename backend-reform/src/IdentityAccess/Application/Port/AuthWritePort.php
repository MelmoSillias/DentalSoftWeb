<?php

namespace App\IdentityAccess\Application\Port;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface AuthWritePort
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function register(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateMe(array $data, ?UploadedFile $file, string $uploadDir): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function changePassword(array $data): array;
}

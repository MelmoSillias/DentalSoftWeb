<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\IdentityAccess\Application\Port\AuthWritePort;
use App\IdentityAccess\Service\AuthService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LegacyAuthWriteAdapter implements AuthWritePort
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(array $data): array
    {
        return $this->authService->register($data);
    }

    public function updateMe(array $data, ?UploadedFile $file, string $uploadDir): array
    {
        return $this->authService->updateMe($data, $file, $uploadDir);
    }

    public function changePassword(array $data): array
    {
        return $this->authService->changePassword($data);
    }
}

<?php

namespace App\IdentityAccess\Application\Command\UpdateUserProfile;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UpdateUserProfileCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?UploadedFile $photo = null,
        public readonly string $uploadDir = '',
    ) {
    }
}

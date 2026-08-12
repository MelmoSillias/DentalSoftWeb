<?php

namespace App\Patient\Application\Command\AddArchiveFile;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AddArchiveFileCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly string $name,
        public readonly UploadedFile $file,
        public readonly string $uploadDir,
    ) {
    }
}

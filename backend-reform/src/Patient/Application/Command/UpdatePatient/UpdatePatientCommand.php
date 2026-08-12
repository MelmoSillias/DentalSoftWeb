<?php

namespace App\Patient\Application\Command\UpdatePatient;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UpdatePatientCommand
{
    /**
     * @param array<string, mixed> $data
     * @param list<UploadedFile> $uploadedArchiveFiles
     * @param list<array{nom?: string, url: string}|string> $existingArchiveFiles
     */
    public function __construct(
        public readonly int $patientId,
        public readonly array $data,
        public readonly ?UploadedFile $photo = null,
        public readonly ?string $uploadDir = null,
        public readonly array $uploadedArchiveFiles = [],
        public readonly array $existingArchiveFiles = [],
    ) {
    }
}

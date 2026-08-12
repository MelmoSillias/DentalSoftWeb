<?php

namespace App\Patient\Application\Command\RemoveArchiveFile;

final class RemoveArchiveFileCommand
{
    public function __construct(
        public readonly int $patientId,
        public readonly string $fileUrl,
    ) {
    }
}

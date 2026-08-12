<?php

namespace App\Patient\Application\Command\RemoveArchiveFile;

use App\Patient\Application\Port\PatientFileStoragePort;
use App\Shared\Application\Bus\CommandHandler;

final class RemoveArchiveFileHandler implements CommandHandler
{
    public function __construct(private readonly PatientFileStoragePort $fileStoragePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RemoveArchiveFileCommand $command): array
    {
        return $this->fileStoragePort->removeArchiveFile($command->patientId, $command->fileUrl);
    }
}

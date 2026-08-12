<?php

namespace App\Patient\Application\Command\AddArchiveFile;

use App\Patient\Application\Port\PatientFileStoragePort;
use App\Shared\Application\Bus\CommandHandler;

final class AddArchiveFileHandler implements CommandHandler
{
    public function __construct(private readonly PatientFileStoragePort $fileStoragePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AddArchiveFileCommand $command): array
    {
        return $this->fileStoragePort->addArchiveFile(
            $command->patientId,
            $command->name,
            $command->file,
            $command->uploadDir,
        );
    }
}

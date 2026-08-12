<?php

namespace App\Patient\Infrastructure\Adapter;

use App\Patient\Application\Port\PatientFileStoragePort;
use App\Patient\Service\PatientService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LegacyPatientFileStorageAdapter implements PatientFileStoragePort
{
    public function __construct(private readonly PatientService $patientService)
    {
    }

    public function storePhoto(
        int $patientId,
        UploadedFile $photo,
        string $uploadDir,
        ?string $currentPhoto,
    ): string {
        return $this->patientService->storePatientPhotoFile($photo, $uploadDir, $currentPhoto, $patientId);
    }

    public function storeArchiveFiles(
        int $patientId,
        string $uploadDir,
        array $uploadedFiles,
        array $existingFiles,
    ): array {
        return $this->patientService->storePatientArchiveFiles(
            $patientId,
            $uploadDir,
            $uploadedFiles,
            $existingFiles,
        );
    }

    public function addArchiveFile(
        int $patientId,
        string $name,
        UploadedFile $file,
        string $uploadDir,
    ): array {
        return $this->patientService->addArchiveFile($patientId, $name, $file, $uploadDir);
    }

    public function removeArchiveFile(int $patientId, string $fileUrl): array
    {
        return $this->patientService->removeArchiveFile($patientId, $fileUrl);
    }
}

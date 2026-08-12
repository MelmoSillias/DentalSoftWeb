<?php

namespace App\Patient\Application\Port;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface PatientFileStoragePort
{
    public function storePhoto(
        int $patientId,
        UploadedFile $photo,
        string $uploadDir,
        ?string $currentPhoto,
    ): string;

    /**
     * @param list<UploadedFile> $uploadedFiles
     * @param list<array{nom?: string, url: string}|string> $existingFiles
     * @return list<array{nom?: string, url: string}|string>
     */
    public function storeArchiveFiles(
        int $patientId,
        string $uploadDir,
        array $uploadedFiles,
        array $existingFiles,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function addArchiveFile(
        int $patientId,
        string $name,
        UploadedFile $file,
        string $uploadDir,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function removeArchiveFile(int $patientId, string $fileUrl): array;
}

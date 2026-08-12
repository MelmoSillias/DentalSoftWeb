<?php

namespace App\Patient\Application\Command\UpdatePatient;

use App\Patient\Application\Command\CreatePatient\CreatePatientHandler;
use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientFileStoragePort;
use App\Patient\Application\Port\PatientInsurancePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Application\Port\PatientSummaryPort;
use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TransactionManager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

final class UpdatePatientHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PatientInsurancePort $insurancePort,
        private readonly PatientFileStoragePort $fileStoragePort,
        private readonly PatientCachePort $cachePort,
        private readonly PatientRealtimePort $realtimePort,
        private readonly PatientSummaryPort $summaryPort,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdatePatientCommand $command): array
    {
        try {
            $patientId = $this->transactionManager->transactional(function () use ($command): int|array {
                $patient = $this->patientRepository->findActiveById(PatientId::fromInt($command->patientId));
                if ($patient === null) {
                    return ['error' => 'Patient non trouvé', 'status' => 404];
                }

                $data = CreatePatientHandler::normalizeSmsPreferenceKeys($command->data);
                $patient->update($data);

                $patientId = $patient->requireId()->toInt();

                if ($command->photo instanceof UploadedFile) {
                    if ($command->uploadDir === null || $command->uploadDir === '') {
                        throw new InvalidArgumentException('Dossier upload manquant pour la photo patient.');
                    }
                    $patient->setPhoto($this->fileStoragePort->storePhoto(
                        $patientId,
                        $command->photo,
                        $command->uploadDir,
                        $patient->getPhoto(),
                    ));
                }

                if ($command->uploadDir !== null && $command->uploadDir !== '') {
                    $archiveFiles = $this->fileStoragePort->storeArchiveFiles(
                        $patientId,
                        $command->uploadDir,
                        $command->uploadedArchiveFiles,
                        $command->existingArchiveFiles,
                    );
                    $patient->setArchiveFiles($archiveFiles);
                } elseif ($command->existingArchiveFiles !== []) {
                    $patient->setArchiveFiles($command->existingArchiveFiles);
                }

                $this->patientRepository->save($patient);
                $this->insurancePort->applyInsuranceProfile($patientId, $data);

                return $patientId;
            });

            if (is_array($patientId)) {
                return $patientId;
            }

            $this->cachePort->clearPatientsCache();
            $this->realtimePort->publishPatientRefresh($patientId, 'updated');

            $summary = $this->summaryPort->getPatientSummary($patientId) ?? [];

            return [
                'success' => true,
                'status' => 200,
                'patient' => $summary,
                ...$summary,
            ];
        } catch (PatientAlreadyDeletedException $e) {
            return ['error' => 'Patient non trouvé', 'status' => 404];
        } catch (PatientDomainException|InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
}

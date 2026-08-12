<?php

namespace App\Patient\Application\Command\RestorePatient;

use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TransactionManager;
use Throwable;

final class RestorePatientHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PatientCachePort $cachePort,
        private readonly PatientRealtimePort $realtimePort,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RestorePatientCommand $command): array
    {
        try {
            $patientId = $this->transactionManager->transactional(function () use ($command): int|array {
                $patient = $this->patientRepository->findDeletedById(PatientId::fromInt($command->patientId));
                if ($patient === null) {
                    return ['error' => 'Patient introuvable dans la corbeille', 'status' => 404];
                }

                $patient->restore();
                $this->patientRepository->save($patient);

                return $patient->requireId()->toInt();
            });

            if (is_array($patientId)) {
                return $patientId;
            }

            $this->cachePort->clearPatientsCache();
            $this->realtimePort->publishPatientRefresh($patientId, 'restored');

            return [
                'success' => true,
                'message' => 'Patient restauré avec succès.',
            ];
        } catch (PatientDomainException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
}

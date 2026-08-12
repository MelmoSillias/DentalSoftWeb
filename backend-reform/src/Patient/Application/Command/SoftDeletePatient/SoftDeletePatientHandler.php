<?php

namespace App\Patient\Application\Command\SoftDeletePatient;

use App\Patient\Application\Port\CloseActiveConsultationsPort;
use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TransactionManager;
use Throwable;

final class SoftDeletePatientHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly CloseActiveConsultationsPort $closeActiveConsultationsPort,
        private readonly PatientCachePort $cachePort,
        private readonly PatientRealtimePort $realtimePort,
        private readonly Clock $clock,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SoftDeletePatientCommand $command): array
    {
        try {
            $patientId = $this->transactionManager->transactional(function () use ($command): int|array {
                $patient = $this->patientRepository->findActiveById(PatientId::fromInt($command->patientId));
                if ($patient === null) {
                    return ['error' => 'Patient non trouvé', 'status' => 404];
                }

                $patientId = $patient->requireId()->toInt();
                $this->closeActiveConsultationsPort->closeActiveConsultations($patientId, $command->actorUserId);

                $patient = $this->patientRepository->findActiveById(PatientId::fromInt($patientId));
                if ($patient === null) {
                    return ['error' => 'Patient non trouvé', 'status' => 404];
                }

                $patient->softDelete($this->clock->now());
                $this->patientRepository->save($patient);

                return $patientId;
            });

            if (is_array($patientId)) {
                return $patientId;
            }

            $this->cachePort->clearPatientsCache();
            $this->realtimePort->publishPatientRefresh($patientId, 'deleted');

            return [
                'success' => true,
                'message' => 'Patient déplacé dans la corbeille.',
            ];
        } catch (PatientAlreadyDeletedException $e) {
            return ['error' => 'Patient non trouvé', 'status' => 404];
        } catch (PatientDomainException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
}

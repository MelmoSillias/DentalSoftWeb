<?php

namespace App\Patient\Application\Command\DeleteAntecedent;

use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TransactionManager;
use Throwable;

final class DeleteAntecedentHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PatientCachePort $cachePort,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeleteAntecedentCommand $command): array
    {
        try {
            $result = $this->transactionManager->transactional(function () use ($command): array {
                $patient = $this->patientRepository->findActiveById(PatientId::fromInt($command->patientId));
                if ($patient === null) {
                    return ['error' => 'Patient introuvable', 'status' => 404];
                }

                try {
                    $patient->removeAntecedent($command->antecedentId);
                } catch (PatientDomainException) {
                    return ['error' => 'Antécédent introuvable', 'status' => 404];
                }

                $this->patientRepository->save($patient);

                return ['success' => true];
            });

            if (!isset($result['error'])) {
                $this->cachePort->clearPatientsCache();
            }

            return $result;
        } catch (PatientAlreadyDeletedException) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        } catch (PatientDomainException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
}

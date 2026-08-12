<?php

namespace App\Patient\Application\Command\AddAntecedent;

use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Domain\Exception\PatientAlreadyDeletedException;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Model\Antecedent;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TransactionManager;
use Throwable;

final class AddAntecedentHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PatientCachePort $cachePort,
        private readonly Clock $clock,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AddAntecedentCommand $command): array
    {
        try {
            $result = $this->transactionManager->transactional(function () use ($command): array {
                $patient = $this->patientRepository->findActiveById(PatientId::fromInt($command->patientId));
                if ($patient === null) {
                    return ['error' => 'Patient introuvable', 'status' => 404];
                }

                $antecedent = Antecedent::create(
                    isset($command->payload['description']) ? (string) $command->payload['description'] : null,
                    isset($command->payload['type']) ? (string) $command->payload['type'] : null,
                    $this->clock->now(),
                );
                $patient->addAntecedent($antecedent);
                $this->patientRepository->save($patient);

                return [
                    'success' => true,
                    'antecedent' => [
                        'id' => $antecedent->getId(),
                        'type' => $antecedent->getType(),
                        'description' => $antecedent->getDescription(),
                        'dateEnregistrement' => $antecedent->getDateEnregistrement()->format('Y-m-d'),
                    ],
                ];
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

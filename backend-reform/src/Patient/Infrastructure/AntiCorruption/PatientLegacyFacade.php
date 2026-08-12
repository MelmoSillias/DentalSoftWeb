<?php

namespace App\Patient\Infrastructure\AntiCorruption;

use App\Patient\Application\Command\CreatePatient\CreatePatientCommand;
use App\Patient\Application\Command\CreatePatientConsultation\CreatePatientConsultationCommand;
use App\Patient\Application\Command\CreatePatientRdv\CreatePatientRdvCommand;
use App\Patient\Application\Command\UpdatePatientDossier\UpdatePatientDossierCommand;
use App\Patient\Application\Query\CheckActiveConsultation\CheckActiveConsultationQuery;
use App\Patient\Application\Query\GetPatientDetails\GetPatientDetailsQuery;
use App\Patient\Application\Query\GetPatientDossier\GetPatientDossierQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;

/**
 * Anti-corruption facade for OTHER modules that still need patient operations.
 *
 * Prefer injecting this (or Patient Application ports) instead of growing new
 * PatientService dependencies. Methods delegate to CommandBus / QueryBus so
 * callers stay behind the DDD boundary while legacy PatientService remains the
 * infrastructure implementation under adapters.
 *
 * Do not use from Patient Presentation/Controller — those talk to buses directly.
 */
final class PatientLegacyFacade
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPatient(array $data, ?int $actorUserId = null): array
    {
        return $this->commandBus->dispatch(new CreatePatientCommand($data, $actorUserId));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRdv(array $data, ?object $actor = null): array
    {
        return $this->commandBus->dispatch(new CreatePatientRdvCommand($data, $actor));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createConsultation(int $patientId, array $payload, ?object $actor = null): array
    {
        return $this->commandBus->dispatch(new CreatePatientConsultationCommand($patientId, $payload, $actor));
    }

    /**
     * @return array{hasActive: bool, consultationId: int|null, hasFiche: bool}
     */
    public function checkConsultationActive(int $patientId): array
    {
        return $this->queryBus->ask(new CheckActiveConsultationQuery($patientId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPatientDetails(int $patientId): ?array
    {
        return $this->queryBus->ask(new GetPatientDetailsQuery($patientId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDossierData(int $patientId): ?array
    {
        return $this->queryBus->ask(new GetPatientDossierQuery($patientId));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateDossier(int $patientId, array $payload): array
    {
        return $this->commandBus->dispatch(new UpdatePatientDossierCommand($patientId, $payload));
    }
}

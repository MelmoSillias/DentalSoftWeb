<?php

namespace App\Patient\Infrastructure\Adapter;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use App\Patient\Application\Port\PatientCreatedSideEffectsPort;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Patient\Service\PatientService;
use Doctrine\ORM\EntityManagerInterface;

final class LegacyPatientCreatedSideEffectsAdapter implements PatientCreatedSideEffectsPort
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PatientService $patientService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function afterCreate(int $patientId, ?int $actorUserId): ?array
    {
        $patient = $this->em->find(Patient::class, $patientId);
        if (!$patient instanceof Patient) {
            return null;
        }

        $actor = null;
        if ($actorUserId !== null) {
            $user = $this->userRepository->find($actorUserId);
            $actor = $user instanceof User ? $user : null;
        }

        return $this->patientService->runAfterCreateSideEffects($patient, $actor);
    }
}

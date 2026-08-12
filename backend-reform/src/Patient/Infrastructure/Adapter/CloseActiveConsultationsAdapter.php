<?php

namespace App\Patient\Infrastructure\Adapter;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\ConsultationRepository;
use App\CareDelivery\Service\ConsultationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use App\Patient\Application\Port\CloseActiveConsultationsPort;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;

final class CloseActiveConsultationsAdapter implements CloseActiveConsultationsPort
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConsultationRepository $consultationRepo,
        private readonly ConsultationService $consultationService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function closeActiveConsultations(int $patientId, ?int $actorUserId): void
    {
        $patient = $this->em->find(Patient::class, $patientId);
        if (!$patient instanceof Patient) {
            return;
        }

        $actor = null;
        if ($actorUserId !== null) {
            $actor = $this->userRepository->find($actorUserId);
            $actor = $actor instanceof User ? $actor : null;
        }

        $activeConsultations = $this->consultationRepo->findBy([
            'patient' => $patient,
            'statut' => 0,
        ]);

        foreach ($activeConsultations as $consultation) {
            if ($patient->getDerniereConsultation()?->getId() === $consultation->getId()) {
                $patient->setDerniereConsultation(null);
                $this->em->flush();
            }

            $this->consultationService->deleteConsultation((int) $consultation->getId(), $actor);
        }
    }
}

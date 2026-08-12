<?php

namespace App\CareDelivery\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\ConsultationRepository as LegacyConsultationRepository;
use App\CareDelivery\Service\ConsultationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class LegacyConsultationWriteAdapter implements ConsultationWritePort
{
    public function __construct(
        private readonly ConsultationService $consultationService,
        private readonly LegacyConsultationRepository $consultationRepository,
    ) {
    }

    public function clotureConsultation(
        int $ficheId,
        int $consultationId,
        ?object $user,
        bool $restrictToMedecin,
        array $payload = [],
    ): void {
        $this->consultationService->clotureConsultation(
            $ficheId,
            $consultationId,
            $user,
            $restrictToMedecin,
            $payload,
        );
    }

    public function updateConsultation(
        int $ficheId,
        int $consultationId,
        array $data,
        ?object $user,
        bool $restrictToMedecin,
    ): void {
        $this->consultationService->updateConsultation(
            $ficheId,
            $consultationId,
            $data,
            $user,
            $restrictToMedecin,
        );
    }

    public function deleteConsultation(int $id, ?object $actor = null): bool
    {
        return $this->consultationService->deleteConsultation(
            $id,
            $actor instanceof User ? $actor : null,
        );
    }

    public function linkOrCreateFiche(
        int $consultationId,
        ?int $ficheId,
        ?object $user,
        bool $restrictToMedecin,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array {
        return $this->consultationService->linkOrCreateFiche(
            $consultationId,
            $ficheId,
            $user,
            $restrictToMedecin,
            $forceCreate,
            $allowDuplicate,
        );
    }

    public function updateOrdonnance(int $ordonnanceId, array $payload): ?array
    {
        return $this->consultationService->updateOrdonnance($ordonnanceId, $payload);
    }

    public function updateFactureLines(
        int $consultationId,
        array $lignes,
        ?string $date = null,
        ?string $time = null,
    ): array {
        $consultation = $this->requireConsultation($consultationId);
        if ($consultation === null) {
            return ['error' => 'Facture non trouvée'];
        }

        return $this->consultationService->updateFactureLines($consultation, $lignes, $date, $time);
    }

    public function verifyMedecinPassword(int $consultationId, string $plainPassword): bool
    {
        return $this->consultationService->verifyConsultationMedecinPassword($consultationId, $plainPassword);
    }

    private function requireConsultation(int $consultationId): ?Consultation
    {
        $consultation = $this->consultationRepository->find($consultationId);

        return $consultation instanceof Consultation ? $consultation : null;
    }
}

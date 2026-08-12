<?php

namespace App\CareDelivery\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationReadPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\ConsultationRepository as LegacyConsultationRepository;
use App\CareDelivery\Service\ConsultationService;
use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\FicheMedicale;
use App\ClinicalRecord\Service\FicheMedicaleService;

final class LegacyConsultationReadAdapter implements ConsultationReadPort
{
    public function __construct(
        private readonly ConsultationService $consultationService,
        private readonly FicheMedicaleService $ficheMedicaleService,
        private readonly LegacyConsultationRepository $consultationRepository,
    ) {
    }

    public function getConsultationDetailsData(int $consultationId): array
    {
        return $this->consultationService->getConsultationDetailsData($consultationId);
    }

    public function getConsultationDetailsContext(int $consultationId): array
    {
        return $this->consultationService->getConsultationDetailsContext($consultationId);
    }

    public function getFicheConsultationJson(
        int $ficheId,
        int $consultationId,
        ?object $user,
        bool $restrictToMedecin,
    ): array {
        [$fiche, $consult] = $this->consultationService->getFicheAndConsultation(
            $ficheId,
            $consultationId,
            $user,
            $restrictToMedecin,
        );

        if ($fiche instanceof FicheMedicale) {
            $ficheData = $this->ficheMedicaleService->getFicheJson($ficheId);

            $consultationData = [
                'id' => $consult->getId(),
                'date' => $consult->getCreatedAt()?->format('Y-m-d H:i'),
                'medecin' => $consult->getMedecin()?->getFullName(),
                'infirmier' => $consult->getInfirmier()?->getFullName(),
                'salle' => $consult->getSalle()?->getNom(),
                'noteSeance' => $consult->getNoteSeance() ?? '',
            ];

            return array_merge($ficheData, [
                'consultation' => $consultationData,
                'actes' => array_map(static fn ($a) => [
                    'dent' => $a->getDent(),
                    'type' => $a->getType(),
                    'description' => $a->getDescription(),
                    'prix' => $a->getPrix(),
                    'quantite' => $a->getQuantite(),
                ], $consult->getActes()->toArray()),
            ]);
        }

        return $this->consultationService->getConsultationJson($fiche->getId(), $consult->getId());
    }

    public function listPendingConsultationsJsonForUser(?object $user, bool $restrictToMedecin): array
    {
        return $this->consultationService->listPendingConsultationsJsonForUser($user, $restrictToMedecin);
    }

    public function getClosedConsultationsData(): array
    {
        return $this->consultationService->getClosedConsultationsData();
    }

    public function consultationsDuJour(?string $date, ?object $user): array
    {
        return $this->consultationService->consultationsDuJour($date, $user);
    }

    public function getReceptionFocusData(?string $date, ?object $user): array
    {
        return $this->consultationService->getReceptionFocusData($date, $user)->toArray();
    }

    public function listOrdonnances(int $consultationId): array
    {
        $consultation = $this->findConsultation($consultationId);
        if ($consultation === null) {
            return [];
        }

        return $this->consultationService->listOrdonnances($consultation);
    }

    public function getOrdonnanceData(int $ordonnanceId): ?array
    {
        return $this->consultationService->getOrdonnanceData($ordonnanceId);
    }

    public function getFactureLines(int $consultationId): ?array
    {
        $consultation = $this->findConsultation($consultationId);
        if ($consultation === null) {
            return null;
        }

        return $this->consultationService->getFactureLines($consultation);
    }

    private function findConsultation(int $consultationId): ?Consultation
    {
        $consultation = $this->consultationRepository->find($consultationId);

        return $consultation instanceof Consultation ? $consultation : null;
    }
}

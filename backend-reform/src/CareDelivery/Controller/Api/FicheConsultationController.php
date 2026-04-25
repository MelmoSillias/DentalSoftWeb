<?php

namespace App\CareDelivery\Controller\Api;

use App\CareDelivery\Service\ConsultationService;
use App\ClinicalRecord\Service\FicheMedicaleService;
use App\Entity\FicheMedicale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiches/{ficheId}', name: 'api_fiche_consultation_')]
class FicheConsultationController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService, private FicheMedicaleService $ficheMedicaleService) {}

    private function restrictToConnectedMedecin(): bool
    {
        return $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
    }

    #[Route('/consultations/{consultationId}/json', name: 'json', methods: ['GET'])]
    public function getJson(int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche, $consult] = $this->consultationService->getFicheAndConsultation(
            $ficheId,
            $consultationId,
            $this->getUser(),
            $this->restrictToConnectedMedecin(),
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

            return new JsonResponse(array_merge($ficheData, ['consultation' => $consultationData, 'actes' => array_map(fn($a) => [
                'dent' => $a->getDent(), 'type' => $a->getType(), 'description' => $a->getDescription(), 'prix' => $a->getPrix(), 'quantite' => $a->getQuantite()
            ], $consult->getActes()->toArray())]));
        }

        return new JsonResponse($this->consultationService->getConsultationJson($fiche->getId(), $consult->getId()));
    }

    #[Route('/motif', methods: ['POST'], name: 'update_motif')]
    public function updateMotif(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $fiche = $this->consultationService->getFicheById($ficheId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateEntretien($ficheId, $data);
        } else {
            $this->consultationService->updateMotif($ficheId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $fiche = $this->consultationService->getFicheById($ficheId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateExamens($ficheId, $data);
        } else {
            $this->consultationService->updateExamens($ficheId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/traitements', methods: ['POST'], name: 'update_traitements')]
    public function updateTraitements(Request $request, int $ficheId): JsonResponse
    {
        $data  = json_decode($request->get('data'), true) ?? [];
        $files = $request->files->get('documentsFiles', []);
        $fiche = $this->consultationService->getFicheById($ficheId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateDocuments($ficheId, $data, $files ?: []);
        } else {
            $this->consultationService->updateTraitements($ficheId, $data, $files ?: []);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $fiche = $this->consultationService->getFicheById($ficheId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateDevis($ficheId, $data);
        } else {
            $this->consultationService->updateDevis($ficheId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/consultations/{consultationId}', methods: ['POST'], name: 'update')]
    public function update(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            // updateConsultation now supports both fiche types via service
            $this->consultationService->updateConsultation(
                $ficheId,
                $consultationId,
                $data,
                $this->getUser(),
                $this->restrictToConnectedMedecin(),
            );
        } catch (ConflictHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/consultations/{consultationId}/cloture', methods: ['POST'], name: 'cloture')]
    public function close(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        try {
            // clotureConsultation will handle both fiche types
            $this->consultationService->clotureConsultation(
                $ficheId,
                $consultationId,
                $this->getUser(),
                $this->restrictToConnectedMedecin(),
            );
        } catch (ConflictHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        return new JsonResponse(['success' => true]);
    }
}
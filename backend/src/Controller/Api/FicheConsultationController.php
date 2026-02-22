<?php

namespace App\Controller\Api;

use App\Service\ConsultationService;
use App\Service\FicheMedicaleService;
use App\Entity\FicheMedicale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiches/{ficheId}/consultations/{consultationId}', name: 'api_fiche_consultation_')]
class FicheConsultationController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService, private FicheMedicaleService $ficheMedicaleService) {}

    #[Route('/json', name: 'json', methods: ['GET'])]
    public function getJson(int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche, $consult] = $this->consultationService->getFicheAndConsultation($ficheId, $consultationId);

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
    public function updateMotif(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        [$fiche, ] = $this->consultationService->getFicheAndConsultation($ficheId, $consultationId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateEntretien($ficheId, $data);
        } else {
            $this->consultationService->updateMotif($ficheId, $consultationId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        [$fiche, ] = $this->consultationService->getFicheAndConsultation($ficheId, $consultationId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateExamens($ficheId, $data);
        } else {
            $this->consultationService->updateExamens($ficheId, $consultationId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/traitements', methods: ['POST'], name: 'update_traitements')]
    public function updateTraitements(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data  = json_decode($request->get('data'), true) ?? [];
        $files = $request->files->get('documentsFiles', []);
        [$fiche, ] = $this->consultationService->getFicheAndConsultation($ficheId, $consultationId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateDocuments($ficheId, $data, $files ?: []);
        } else {
            $this->consultationService->updateTraitements($ficheId, $consultationId, $data, $files ?: []);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        [$fiche, ] = $this->consultationService->getFicheAndConsultation($ficheId, $consultationId);

        if ($fiche instanceof FicheMedicale) {
            $this->ficheMedicaleService->updateDevis($ficheId, $data);
        } else {
            $this->consultationService->updateDevis($ficheId, $consultationId, $data);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('', methods: ['POST'], name: 'update')]
    public function update(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        // updateConsultation now supports both fiche types via service
        $this->consultationService->updateConsultation($ficheId, $consultationId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/cloture', methods: ['POST'], name: 'cloture')]
    public function close(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        // clotureConsultation will handle both fiche types
        $this->consultationService->clotureConsultation($ficheId, $consultationId);
        return new JsonResponse(['success' => true]);
    }
}
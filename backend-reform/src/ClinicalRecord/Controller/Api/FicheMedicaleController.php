<?php

namespace App\ClinicalRecord\Controller\Api;

use App\ClinicalRecord\Service\FicheMedicaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiches-medicales/{ficheId}', name: 'api_fiche_medicale_')]
class FicheMedicaleController extends AbstractController
{
    public function __construct(private FicheMedicaleService $ficheMedicaleService) {}

    #[Route('/json', methods: ['GET'], name: 'json')]
    public function getJson(int $ficheId): JsonResponse
    {
        return new JsonResponse($this->ficheMedicaleService->getFicheJson($ficheId));
    }

    #[Route('/entretien', methods: ['POST'], name: 'update_entretien')]
    public function updateEntretien(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->ficheMedicaleService->updateEntretien($ficheId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->ficheMedicaleService->updateExamens($ficheId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/bilans', methods: ['POST'], name: 'update_bilans')]
    public function updateBilans(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->ficheMedicaleService->updateBilans($ficheId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/plan-traitement', methods: ['POST'], name: 'update_plan_traitement')]
    public function updatePlanTraitement(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->ficheMedicaleService->updatePlanTraitement($ficheId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/documents', methods: ['POST'], name: 'update_documents')]
    public function updateDocuments(Request $request, int $ficheId): JsonResponse
    {
        $data = $request->get('data');
        $payload = $data ? json_decode($data, true) : json_decode($request->getContent(), true);
        $files = $request->files->get('documentsFiles', []);
        $this->ficheMedicaleService->updateDocuments($ficheId, $payload ?? [], $files);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->ficheMedicaleService->updateDevis($ficheId, $data);
        return new JsonResponse(['success' => true]);
    } 

    
}

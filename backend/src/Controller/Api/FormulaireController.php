<?php

namespace App\Controller\Api;

use App\Service\FormulaireService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/formulaires', name: 'api_formulaires_')]
class FormulaireController extends AbstractController
{
    public function __construct(private FormulaireService $formulaireService)
    {
    }

    #[Route('/medical/default', name: 'medical_default', methods: ['GET'])]
    public function getDefaultMedicalForm(): JsonResponse
    {
        $formulaire = $this->formulaireService->ensureDefaultPublishedForm();

        return $this->json($this->formulaireService->serializeFormulaire($formulaire));
    }

    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $source = $this->formulaireService->findFormulaireOrFail($id);
        $duplicated = $this->formulaireService->duplicateFormulaire($source, $payload['label'] ?? null);

        return $this->json($this->formulaireService->serializeFormulaire($duplicated), 201);
    }

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse
    {
        $formulaire = $this->formulaireService->findFormulaireOrFail($id);
        $published = $this->formulaireService->publishFormulaire($formulaire);

        return $this->json($this->formulaireService->serializeFormulaire($published));
    }
}
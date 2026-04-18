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

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->formulaireService->listFormulaires());
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id): JsonResponse
    {
        $formulaire = $this->formulaireService->findFormulaireOrFail($id);

        return $this->json($this->formulaireService->serializeFormulaire($formulaire));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $created = $this->formulaireService->createFormulaireFromPayload($payload);

        return $this->json($this->formulaireService->serializeFormulaire($created), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $formulaire = $this->formulaireService->findFormulaireOrFail($id);
        $updated = $this->formulaireService->updateFormulaireFromPayload($formulaire, $payload);

        return $this->json($this->formulaireService->serializeFormulaire($updated));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\\d+'])]
    public function delete(int $id): JsonResponse
    {
        $formulaire = $this->formulaireService->findFormulaireOrFail($id);
        $this->formulaireService->deleteFormulaire($formulaire);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function duplicate(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $source = $this->formulaireService->findFormulaireOrFail($id);
        $duplicated = $this->formulaireService->duplicateFormulaire($source, $payload['label'] ?? null);

        return $this->json($this->formulaireService->serializeFormulaire($duplicated), 201);
    }

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function publish(int $id): JsonResponse
    {
        $formulaire = $this->formulaireService->findFormulaireOrFail($id);
        $published = $this->formulaireService->publishFormulaire($formulaire);

        return $this->json($this->formulaireService->serializeFormulaire($published));
    }
}
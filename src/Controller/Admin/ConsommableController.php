<?php

namespace App\Controller\Admin;

use App\Entity\Consommable;
use App\Service\ConsommableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ConsommableController extends AbstractController
{
    public function __construct(private ConsommableService $consommableService)
    {
    }

    #[Route('admin/consommables', name: 'app_admin_consumables')]
    public function Consumables(): Response
    {
        $data = $this->consommableService->listConsumablesWithVariations();

        return $this->render('admin/consumables.html.twig', [
            'active_page' => 'consumables',
            'consommables' => $data['consommables'],
            'variations' => $data['variations'],
        ]);
    }

    #[Route('/api/consommables/add', name: 'api_consommable_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $result = $this->consommableService->addConsommable(
            $request->request->all(),
            $this->getUser()
        );

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consommables/{id}/edit', name: 'api_consommable_edit', methods: ['POST'])]
    public function edit(Request $request, Consommable $consommable): JsonResponse
    {
        $result = $this->consommableService->editConsommable($consommable, $request->request->all());

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/api/consommables/{id}/retrait', name: 'api_consommable_retrait', methods: ['POST'])]
    public function retrait(Consommable $consommable, Request $request): JsonResponse
    {
        $result = $this->consommableService->retrait($consommable, $request->request->all());

        return $this->json($result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consommables/{id}', name: 'api_consommable_details', methods: ['GET'])]
    public function getConsommableDetails(Consommable $consommable = null): JsonResponse
    {
        if (!$consommable) {
            return $this->json(['error' => 'Consommable not found'], 404);
        }

        return $this->json($this->consommableService->getConsommableDetails($consommable));
    }

    #[Route('/api/consommables/{id}/add-stock', name: 'api_consommable_add_stock', methods: ['POST'])]
    public function addStock(Consommable $consommable, Request $request): JsonResponse
    {
        $result = $this->consommableService->addStock($consommable, $request->request->all(), $this->getUser());

        return $this->json($result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consommables/{id}/delete', name: 'api_consommable_delete', methods: ['POST'])]
    public function delete(Consommable $consommable, Request $request): JsonResponse
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $consommable->getId(), $submittedToken)) {
            return $this->json(['error' => 'Invalid CSRF token.'], 400);
        }

        return $this->json($this->consommableService->deleteConsommable($consommable));
    }

    
    #[Route('/api/stocks', name: 'api_stocks', methods: ['GET'])]
    public function fetchStocks(Request $request): JsonResponse
    {
        $data = $this->consommableService->fetchStocks(
            $request->query->get('start'),
            $request->query->get('end')
        );

        return new JsonResponse($data);
    }

    #[Route('/api/consommables', name: 'api_consommables', methods: ['GET'])]
    public function fetchConsommables(): JsonResponse
    {
        return new JsonResponse($this->consommableService->fetchConsommables());
    }
}


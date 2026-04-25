<?php

namespace App\Inventory\Controller\Api;

use App\Inventory\Entity\Consommable;
use App\IdentityAccess\Entity\User;
use App\Inventory\Service\ConsommableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ConsommableController extends AbstractController
{
    public function __construct(
        private ConsommableService $consommableService,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('/api/consumables', name: 'api_consommable_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $actor = $this->getUser();

        $data = empty($request->request->all()) ? json_decode($request->getContent(), true) : $request->request->all();
        $result = $this->consommableService->addConsommable(
            $data,
            $actor instanceof User ? $actor : null,
        );

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consumables/{id}', name: 'api_consommable_edit', methods: ['PUT'])]
    public function edit(Request $request, Consommable $consommable): JsonResponse
    {
        $actor = $this->getUser();
        $data = empty($request->request->all()) ? json_decode($request->getContent(), true) : $request->request->all();
        $result = $this->consommableService->editConsommable(
            $consommable,
            $data,
            $actor instanceof User ? $actor : null,
        );

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/api/consumables/{id}/withdraw', name: 'api_consommable_retrait', methods: ['POST'])]
    public function withdraw(Consommable $consommable, Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $data = empty($request->request->all()) ? json_decode($request->getContent(), true) : $request->request->all();
        $result = $this->consommableService->retrait(
            $consommable,
            $data,
            $actor instanceof User ? $actor : null,
        );

        return $this->json($result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consumables/{id}', name: 'api_consommable_details', methods: ['GET'])]
    public function getDetails(Consommable $consommable): JsonResponse
    {
        if (!$consommable) {
            return $this->json(['error' => 'Consommable not found'], 404);
        }

        return $this->json($this->consommableService->getConsommableDetails($consommable));
    }

    #[Route('/api/consumables/{id}/stock', name: 'api_consommable_add_stock', methods: ['POST'])]
    public function addStock(Consommable $consommable, Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $data = empty($request->request->all()) ? json_decode($request->getContent(), true) : $request->request->all();
        $result = $this->consommableService->addStock(
            $consommable,
            $data,
            $actor instanceof User ? $actor : null,
        );

        return $this->json($result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/consumables/{id}', name: 'api_consommable_delete', methods: ['DELETE'])]
    public function delete(Consommable $consommable, Request $request): JsonResponse
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $consommable->getId(), $submittedToken)) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }

        $actor = $this->getUser();
        $result = $this->consommableService->deleteConsommable(
            $consommable,
            $actor instanceof User ? $actor : null,
        );

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/api/consumables', name: 'api_consommables', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->consommableService->fetchConsommables();

        $rowsWithTokens = array_map(function (array $row) {
            $id = $row['id'] ?? null;
            if (!$id) {
                return $row;
            }

            $row['deleteToken'] = $this->csrfTokenManager->getToken('delete' . $id)->getValue();

            return $row;
        }, $rows);

        return $this->json($rowsWithTokens);
    }

    #[Route('/api/stocks', name: 'api_stocks', methods: ['GET'])]
    public function fetchStocks(Request $request): JsonResponse
    {
        $consommableId = $request->query->get('consumableId');
        $consommableId = $consommableId !== null && $consommableId !== 'null' ? (int) $consommableId : null;
        $start = $request->query->get('start') != 'null' ? $request->query->get('start') : null;
        $end = $request->query->get('end') != 'null' ? $request->query->get('end') : null;

        $data = $this->consommableService->fetchStocks(
            $consommableId,
            $start,
            $end
        );

        return new JsonResponse($data);
    }
}
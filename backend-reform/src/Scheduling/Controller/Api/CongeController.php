<?php

namespace App\Scheduling\Controller\Api;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Scheduling\Service\CongeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/conges')]
class CongeController extends AbstractController
{
    public function __construct(private CongeService $congeService)
    {
    }

    #[Route('', name: 'api_conges_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->congeService->addConge(
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user : null,
        );

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('', name: 'api_conges_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return $this->json($this->congeService->listConges([
            'employeId' => $request->query->get('employeId'),
            'type' => $request->query->get('type'),
            'start' => $request->query->get('start'),
            'end' => $request->query->get('end'),
        ]));
    }

    #[Route('/employees', name: 'api_conges_employees', methods: ['GET'])]
    public function employeesWithConges(): JsonResponse
    {
        return $this->json($this->congeService->listEmployesWithConges());
    }

    #[Route('/{id}', name: 'api_conges_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->congeService->updateConge(
            $id,
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user : null,
        );

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/{id}', name: 'api_conges_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->congeService->deleteConge(
            $id,
            $user instanceof User ? $user : null,
        );

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }
}
<?php

namespace App\Controller\Api;

use App\IdentityAccess\Entity\User;
use App\Service\CongeService;
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
    public function list(): JsonResponse
    {
        return $this->json($this->congeService->listConges());
    }

    #[Route('/employees', name: 'api_conges_employees', methods: ['GET'])]
    public function employeesWithConges(): JsonResponse
    {
        return $this->json($this->congeService->listEmployesWithConges());
    }
}
<?php

namespace App\Controller\Admin;

use App\Service\CongeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
 
final class CongeController extends AbstractController
{
    public function __construct(private CongeService $congeService)
    {
    }

    #[Route('/api/conges', name: 'api_add_conge', methods: ['POST'])]
    public function addConge(Request $request): JsonResponse
    {
        $result = $this->congeService->addConge(json_decode($request->getContent(), true) ?? []);

        return $this->json($result['error'] ?? $result, $result['status'] ?? (isset($result['error']) ? 400 : 200));
    }

    #[Route('/api/conges/all', name: 'api_all_conges', methods: ['GET'])]
    public function getAllConges(): JsonResponse
    {
        return $this->json($this->congeService->listConges());
    }

    #[Route('/api/employes/conges', name: 'api_employes_conges', methods: ['GET'])]
    public function getEmployesWithConges(): JsonResponse
    {
        return $this->json($this->congeService->listEmployesWithConges());
    }

    
}

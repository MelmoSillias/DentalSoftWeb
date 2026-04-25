<?php

namespace App\ClinicalRecord\Controller\Api;

use App\ClinicalRecord\Service\FicheMedicaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}

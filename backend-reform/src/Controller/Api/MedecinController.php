<?php

namespace App\Controller\Api;

use App\IdentityAccess\Repository\EmployeRepository;
use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MedecinController extends AbstractController
{
    public function __construct(
        private ConsultationService $medecinService,
    ) {
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getAllMedecins(): JsonResponse
    {
        return new JsonResponse($this->medecinService->listMedecins());
    }

    #[Route('/api/infirmiers', name: 'api_infirmiers', methods: ['GET'])]
    public function getAllInfirmiers(): JsonResponse
    {
        return new JsonResponse($this->medecinService->listInfirmiers());
    }

}
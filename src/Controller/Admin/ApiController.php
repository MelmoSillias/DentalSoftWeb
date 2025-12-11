<?php

namespace App\Controller\Admin;

use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class ApiController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService)
    {
    }

    #[Route('/api/consultations/closed', name: 'api_consultations_closed', methods: ['GET'])]
    public function getClosedConsultations(): JsonResponse
    {
        return new JsonResponse($this->consultationService->getClosedConsultationsData());
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getAllMedecins(): JsonResponse
    {
        return new JsonResponse($this->consultationService->listMedecins());
    }
}

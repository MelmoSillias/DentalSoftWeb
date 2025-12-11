<?php

namespace App\Controller\Reception;

use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConsultationController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService)
    {
    }

    #[Route('/reception/consultation/en-attente', name: 'app_reception_consultations_pending')]
    public function pendingConsultations(): Response
    {
        $context = $this->consultationService->getPendingConsultationsContextForUser(null, false);

        return $this->render('reception/pendingconsultations.html.twig', [
            'consultations' => $context['consultations'],
            'consultationsData' => $context['data'],
            'active_page' => 'consultations_pending',
        ]);
    }

    #[Route('/reception/consultation/en-attente.json', name: 'app_reception_consultation_en_attente_json', methods:['GET'])]
    public function enAttenteJson(): JsonResponse
    {
        $context = $this->consultationService->getPendingConsultationsContextForUser(null, false);
        return $this->json($context['data']);
    }
}
<?php

namespace App\Controller\Admin;

use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/api/fiche/{ficheId}/consultation/{consultationId}', name: 'api_fiche_consultation_')]
class ConsultationApiController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService)
    {
    }

    #[Route('/json', name: 'json', methods: ['GET'])]
    public function getConsJson(int $ficheId, int $consultationId): JsonResponse
    {
        return new JsonResponse($this->consultationService->getConsultationJson($ficheId, $consultationId));
    }

    #[Route('/update-motif', methods: ['POST'], name: 'update_motif')]
    public function updateMotif(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->consultationService->updateMotif($ficheId, $consultationId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->consultationService->updateExamens($ficheId, $consultationId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-traitements', methods: ['POST'], name: 'update_traitements')]
    public function updateTraitements(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data  = json_decode($request->get('data'), true) ?? [];
        $files = $request->files->get('documentsFiles', []);
        $this->consultationService->updateTraitements($ficheId, $consultationId, $data, $files);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->consultationService->updateDevis($ficheId, $consultationId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update', methods: ['POST'], name: 'update')]
    public function updateConsultation(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->consultationService->updateConsultation($ficheId, $consultationId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/cloture', methods: ['POST'], name: 'cloture')]
    public function clotureConsultation(int $ficheId, int $consultationId): JsonResponse
    {
        $this->consultationService->clotureConsultation($ficheId, $consultationId);
        return new JsonResponse(['success' => true]);
    }

  


}

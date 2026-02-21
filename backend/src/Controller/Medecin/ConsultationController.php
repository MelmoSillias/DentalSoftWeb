<?php
namespace App\Controller\Medecin;

use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ConsultationController extends AbstractController
{
    public function __construct(private ConsultationService $consultationService)
    {
    }

    #[Route('/medecin/consultation/en-attente', name: 'app_medecin_consultations_pending')]
    public function pendingConsultations(): Response
    {
        $context = $this->consultationService->getPendingConsultationsContextForUser($this->getUser(), true);

        return $this->render('medecin/pendingconsultations.html.twig', [
            'consultations' => $context['consultations'],
            'consultationsData' => $context['data'],
            'active_page' => 'consultations_pending',
        ]);
    }

    #[Route('/medecin/consultation/en-attente.json', name: 'app_medecin_consultation_en_attente_json', methods:['GET'])]
    public function enAttenteJson(): JsonResponse
    {
        $context = $this->consultationService->getPendingConsultationsContextForUser($this->getUser(), true);

        return $this->json($context['data']);
    }

    #[Route('/medecin/consultation/{id}/editer', name: 'app_medecin_consultation_edit', methods: ['GET'])]
    public function editConsultation(int $id): Response
    {
        $context = $this->consultationService->getEditConsultationContext($id);

        return $this->render('medecin/editConsultation.html.twig', array_merge($context, [
            'id' => $id,
            'patient' => $context['consultation']->getPatient(),
            'active_page' => 'consultations_pending',
        ]));
    }

    #[Route('/medecin/consultation/{id}/editer/new', name: 'app_medecin_consultation_edit_new', methods: ['GET'])]
    public function editConsultationNewFiche(int $id): Response
    {
        $context = $this->consultationService->getEditConsultationContext($id, true);

        return $this->render('medecin/editConsultation.html.twig', array_merge($context, [
            'id' => $id,
            'patient' => $context['consultation']->getPatient(),
            'active_page' => 'consultations_pending',
        ]));
    }

    #[Route('/medecin/consultation/{id}/details', name: 'app_medecin_consultation_details')]
    public function consultationDetails(int $id): Response
    {
        $context = $this->consultationService->getConsultationDetailsData($id);

        return $this->render('medecin/consultationDetails.html.twig', [
            'consultation' => $context['entity'],
            'actes' => $context['data']['actes'],
            'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/medecin/consultation/{id}/details.json', name: 'app_medecin_consultation_details_json', methods: ['GET'])]
    public function consultationDetailsJson(int $id): JsonResponse
    {
        return new JsonResponse($this->consultationService->getConsultationDetailsData($id)['data']);
    }

    #[Route('/medecin/consultations/closed', name: 'app_medecin_consultations_closed')]
    public function closedConsultations(): Response
    {
        return $this->render('medecin/closedconsultations.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/medecin/consultation/liste', name: 'app_medecin_consultations_liste')]
    public function ListeAllConsultations(): Response
    {
           
        return $this->render('medecin/consultations.html.twig', [ 
            'active_page'       => 'consultations_liste',
        ]);
    }
}

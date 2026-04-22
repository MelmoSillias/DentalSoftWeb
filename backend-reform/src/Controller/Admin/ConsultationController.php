<?php

namespace App\Controller\Admin;

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

    #[Route('/admin/consultation/liste', name: 'consultations_liste')]
    public function ListeAllConsultations(): Response
    {
        return $this->render('admin/consultations.html.twig', [
            'active_page' => 'consultations_liste',
        ]);
    }

    #[Route('/admin/consultation/en-attente', name: 'consultations_pending')]
    public function pendingConsultations(): Response
    {
        $context = $this->consultationService->getPendingConsultationsContext();

        return $this->render('admin/pendingconsultations.html.twig', [
            'consultations' => $context['consultations'],
            'consultationsData' => $context['consultationsData'],
            'active_page' => 'consultations_pending',
        ]);
    }

    #[Route('/admin/consultation/{id}/editer', name: 'consultation_edit', methods: ['GET'])]
    public function editConsultation(int $id): Response
    {
        $context = $this->consultationService->getEditConsultationContext($id, false);

        return $this->render('admin/editConsultation.html.twig', [
            'id' => $id,
            'consultation' => $context['consultation'],
            'patient' => $context['consultation']->getPatient(),
            'fiche' => $context['fiche'],
            'consultationsFiche' => $context['consultationsFiche'],
            'medecins' => $context['medecins'],
            'infirmiers' => $context['infirmiers'],
            'salles' => $context['salles'],
            'active_page' => 'consultations_pending',
        ]);
    }

    #[Route('/admin/consultation/{id}/editer/new', name: 'consultation_edit_new', methods: ['GET'])]
    public function editConsultationNewFiche(int $id): Response
    {
        $context = $this->consultationService->getEditConsultationContext($id, true);

        return $this->render('admin/editConsultation.html.twig', [
            'id' => $id,
            'consultation' => $context['consultation'],
            'patient' => $context['consultation']->getPatient(),
            'fiche' => $context['fiche'],
            'consultationsFiche' => $context['consultationsFiche'],
            'medecins' => $context['medecins'],
            'infirmiers' => $context['infirmiers'],
            'salles' => $context['salles'],
            'active_page' => 'consultations_pending',
        ]);
    }

    #[Route('/admin/consultation/{id}/details', name: 'consultation_details')]
    public function consultationDetails(int $id): Response
    {
        $context = $this->consultationService->getConsultationDetailsContext($id);

        return $this->render('admin/consultationDetails.html.twig', [
            'consultation' => $context['consultation'],
            'actes' => $context['actes'],
            'active_page' => 'consultations_closed',
        ]);
    }

    #[Route('/admin/consultations/closed', name: 'consultations_closed')]
    public function closedConsultations(): Response
    {
        return $this->render('admin/closedconsultations.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'consultations_closed'
        ]);
    }
}
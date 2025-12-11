<?php

namespace App\Controller\Admin;

use App\Entity\Consultation;
use App\Service\ConsultationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
            'active_page' => 'consultations_pending',
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


    // src/Controller/Admin/ConsultationController.php
    #[Route('/admin/consultation/en-attente.json', name: 'consultation_en_attente_json', methods: ['GET'])]
    public function enAttenteJson(): JsonResponse
    {
        return $this->json($this->consultationService->listPendingConsultationsJson());
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

    #[Route('/admin/consultation/{id}/details.json', name: 'consultation_details_json', methods: ['GET'])]
    public function consultationDetailsJson(int $id): JsonResponse
    {
        return new JsonResponse($this->consultationService->getConsultationDetailsData($id));
    }


    #[Route('/api/consultation/{id}', name: 'api_consultation_delete', methods: ['DELETE'])]
    public function deleteConsultation(int $id): JsonResponse
    {
        $deleted = $this->consultationService->deleteConsultation($id);

        if (!$deleted) {
            return $this->json(['message' => 'Consultation introuvable'], 404);
        }

        return $this->json(['message' => 'Consultation supprimée avec succès']);
    }


    #[Route('/admin/consultations/closed', name: 'consultations_closed')]
    public function closedConsultations(): Response
    {
        return $this->render('admin/closedconsultations.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'consultations_closed'
        ]);
    }

    
    #[Route('/api/consultation/{consultation}/facture', name: 'by_consultation', methods: ['GET'])]
    public function GetFacturebyConsultation(Consultation $consultation): JsonResponse
    {
        $lignes = $this->consultationService->getFactureLines($consultation);

        if ($lignes === null) {
            return new JsonResponse(['error' => 'Facture non trouvée'], 404);
        }

        return new JsonResponse($lignes);
    }

    /**
     * Met à jour les lignes de la facture (Devis) pour une consultation donnée.
     * Expects JSON { "lignes": [ { designation, quantite, montant, description? }, … ] }
     */
    #[Route('/api/consultation/{consultation}/facture/update', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Consultation $consultation): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['lignes'])) {
            return new JsonResponse(['error' => 'Payload invalide'], 400);
        }

        $result = $this->consultationService->updateFactureLines($consultation, $data['lignes']);

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/consultations/jour', name: 'api_consultations_day', methods: ['GET'])]
    public function consultationsDuJour(Request $req): JsonResponse
    {
        $result = $this->consultationService->consultationsDuJour($req->get('date'), $this->getUser());

        return new JsonResponse($result);
    }
}
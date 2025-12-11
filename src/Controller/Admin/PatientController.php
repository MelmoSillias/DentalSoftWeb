<?php

namespace App\Controller\Admin;

use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PatientController extends AbstractController
{
    public function __construct(private PatientService $patientService)
    {
    }

    #[Route('/admin/patients', name: 'app_admin_patient')]
    public function patient(): Response
    {
        $context = $this->patientService->getPatientsPageContext();

        return $this->render('admin/patient.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'patients',
            'salles' => $context['salles'],
        ]);
    }

    #[Route('/api/patient/{id}/dossier', name: 'api_patient_dossier_get', methods: ['GET'])]
    public function getDossier(int $id): JsonResponse
    {
        $data = $this->patientService->getDossierData($id);

        if ($data === null) {
            return $this->json(['error' => 'Patient introuvable'], 404);
        }

        return $this->json($data);
    }

    /**
     * PUT /api/patient/{id}/dossier/update
     * Met à jour les champs simples et collections du dossier patient
     */
    #[Route('/api/patient/{id}/dossier/update', name: 'api_patient_dossier_update', methods: ['PUT'])]
    public function updateDossier(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $result = $this->patientService->updateDossier($id, $payload ?? []);

        if (!empty($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/patient/{id}/dossier/print/infosperso', name: 'patient_print_infos_perso', methods: ['GET'])]
    public function print(int $id): Response
    {
        $patient = $this->patientService->getPrintInfosPersoContext($id);
        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé.');
        }

        return $this->render('admin/printinfosperso.html.twig', [
            'patient' => $patient,
        ]);
    }

    #[Route('/api/patient/{patientId}/fiche/{ficheId}/print', name: 'patient_fiche_print', methods: ['GET'])]
    public function printFiche(int $patientId, int $ficheId): Response
    {
        $context = $this->patientService->getPrintFicheContext($patientId, $ficheId);

        if (!$context) {
            throw $this->createNotFoundException('Fiche introuvable pour ce patient.');
        }

        return $this->render('pages_bases/fiche_print.html.twig', $context);
    }
    
}

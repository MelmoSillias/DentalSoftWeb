<?php

namespace App\Controller;

use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientAPIController extends AbstractController
{
    public function __construct(private PatientService $patientService)
    {
    }

    #[Route('/api/patients', name: 'api_patients', methods: ['GET'])]
    public function getPatients(): JsonResponse
    {
        return $this->json($this->patientService->listPatients());
    }

    #[Route('/api/patients/medecin', name: 'api_patients_by_medecin', methods: ['GET'])]
    public function getPatientsByMedecin(): JsonResponse
    {
        $result = $this->patientService->listPatientsByMedecin($this->getUser());

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }



    #[Route('/api/patient/add', name: 'api_patient_add', methods: ['POST'])]
    public function addPatient(Request $request): JsonResponse
    {
        $result = $this->patientService->addPatient(json_decode($request->getContent(), true) ?? []);

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Patient ajouté avec succès', 'patientId' => $result['patientId']], $result['status'] ?? 201);
    }

    #[Route('/api/patient/{id}/update', name: 'api_patient_update', methods: ['POST'])]
    public function updatePatient(int $id, Request $request): JsonResponse
    {
        $result = $this->patientService->updatePatient($id, json_decode($request->getContent(), true) ?? []);

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Patient mis à jour avec succès'], $result['status'] ?? 200);
    }

    #[Route('/api/patient/{id}/consultation-en-cours', name: 'api_consultation_check_active', methods: ['GET'])]
    public function checkConsultationEnCours(int $id): JsonResponse
    {
        return $this->json(['hasActive' => $this->patientService->checkConsultationActive($id)]);
    }

    #[Route('/api/patients/search', name: 'api_patients_search', methods: ['GET'])]
    public function searchPatients(Request $request): JsonResponse
    {
        $results = $this->patientService->searchPatients($request->query->get('term', ''));
        return $this->json(['results' => $results]);
    }




    #[Route('/api/patient/{id}', name: 'api_patient_details', methods: ['GET'])]
    public function getPatientDetails(int $id): JsonResponse
    {
        $data = $this->patientService->getPatientDetailsData($id);

        if ($data === null) {
            return $this->json(['message' => 'Patient non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($data);
    }

    // src/Controller/PatientAPIController.php
    #[Route('/admin/patient/{id}/dossier', name: 'app_admin_patient_dossier')]
    public function AdminDossierMedical(int $id): Response
    {
        $patient = $this->patientService->getPatientWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }

    #[Route('/medecin/patient/{id}/dossier', name: 'app_medecin_patient_dossier')]
    public function MedecinDossierMedical(int $id): Response
    {
        $patient = $this->patientService->getPatientWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical_medecin.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }

    #[Route('/api/consultation/create', name: 'api_consultation_create', methods: ['POST'])]
    public function createConsultation(Request $request): JsonResponse
    {
        $result = $this->patientService->createConsultation(json_decode($request->getContent(), true) ?? []);

        if (isset($result['error'])) {
            return $this->json(['success' => false, 'error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json([
            'success' => true,
            'consultation_id' => $result['consultation_id'] ?? null,
            'paiement_id' => $result['paiement_id'] ?? null,
        ], $result['status'] ?? 200);
    }

    #[Route('/api/patient/{id}/rdv/create', name: 'api_patient_rdv_create', methods: ['POST'])]
    public function createRdv(Request $request): JsonResponse
    {
        $result = $this->patientService->createRdv(json_decode($request->getContent(), true) ?? []);

        if (isset($result['error'])) {
            return $this->json(['success' => false, 'error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true, 'rdv_id' => $result['rdv_id']], $result['status'] ?? 201);
    }
}

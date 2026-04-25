<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends AbstractController
{
    public function __construct(private PatientService $patientService)
    {
    }

    #[Route('/api/patients', name: 'api_patients', methods: ['GET'])]
    public function getPatients(Request $request): JsonResponse
    {
        if (!$request->query->has('page') && !$request->query->has('limit')) {
            return $this->json($this->patientService->listPatients());
        }

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $query = trim((string) ($request->query->get('q') ?? $request->query->get('term') ?? ''));
        $query = $query !== '' ? $query : null;
        $sortField = $request->query->get('sortField');
        $sortOrder = $request->query->get('sortOrder');

        return $this->json($this->patientService->listPatientsPaginated($page, $limit, $query, $sortField, $sortOrder));
    }

    #[Route('/api/patients/medecin', name: 'api_patients_by_medecin', methods: ['GET'])]
    public function getPatientsByMedecin(Request $request): JsonResponse
    {
        if (!$request->query->has('page') && !$request->query->has('limit')) {
            $result = $this->patientService->listPatientsByMedecin($this->getUser());

            if (isset($result['error'])) {
                return $this->json(['error' => $result['error']], $result['status'] ?? 400);
            }

            return $this->json($result);
        }

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $query = trim((string) ($request->query->get('q') ?? $request->query->get('term') ?? ''));
        $query = $query !== '' ? $query : null;
        $sortField = $request->query->get('sortField');
        $sortOrder = $request->query->get('sortOrder');

        $result = $this->patientService->listPatientsByMedecinPaginated($this->getUser(), $page, $limit, $query, $sortField, $sortOrder);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/patient/add', name: 'api_patient_add', methods: ['POST'])]
    public function addPatient(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->patientService->addPatient(
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user : null,
        );

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Patient ajouté avec succès', 'patientId' => $result['patientId']], $result['status'] ?? 201);
    }

    #[Route('/api/patient/{id}/update', name: 'api_patient_update', methods: ['POST'])]
    public function updatePatient(int $id, Request $request): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type', '');
        $data = str_starts_with($contentType, 'application/json')
            ? (json_decode($request->getContent(), true) ?: [])
            : $request->request->all();
        $file = $request->files->get('photo');

        $result = $this->patientService->updatePatient(
            $id,
            $data,
            $file,
            dirname(__DIR__, 3) . '/public/uploads',
        );

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/api/patient/{id}/consultation-en-cours', name: 'api_consultation_check_active', methods: ['GET'])]
    public function checkConsultationEnCours(int $id): JsonResponse
    {
        return $this->json($this->patientService->checkConsultationActive($id));
    }

    #[Route('/api/patients/search', name: 'api_patients_search', methods: ['GET'])]
    public function searchPatients(Request $request): JsonResponse
    {
        $term = $request->query->get('q') ?? $request->query->get('term', '');
        $limit = (int) $request->query->get('limit', 20);
        $results = $this->patientService->searchPatients((string) $term, $limit);
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

    #[Route('/api/patient/{patientId}/consultation/create', name: 'api_consultation_create', methods: ['POST'])]
    public function createConsultation(int $patientId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $payload['patient_id'] = $patientId; // Assurer que le patient_id est défini

        $user = $this->getUser();
        $result = $this->patientService->createConsultation(
            $payload,
            $user instanceof User ? $user : null,
        );

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
        $user = $this->getUser();
        $result = $this->patientService->createRdv(
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user : null,
        );

        if (isset($result['error'])) {
            return $this->json(['success' => false, 'error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true, 'rdv_id' => $result['rdv_id']], $result['status'] ?? 201);
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

    #[Route('/api/patient/{id}/consultations', name: 'api_patient_consultations', methods: ['GET'])]
    public function getPatientConsultations(int $id): JsonResponse
    {
        return $this->json($this->patientService->listPatientConsultations($id));
    }

    #[Route('/api/patient/{id}/portal-user', name: 'api_patient_portal_user_get', methods: ['GET'])]
    public function getPortalUser(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->patientService->getPatientPortalAccountData($id);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/portal-user/create', name: 'api_patient_portal_user_create', methods: ['POST'])]
    public function createPortalUser(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->patientService->createPatientPortalAccount($id);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/portal-user/reset-password', name: 'api_patient_portal_user_reset_password', methods: ['POST'])]
    public function resetPortalUserPassword(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->patientService->resetPatientPortalPassword($id);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/portal-user/active', name: 'api_patient_portal_user_toggle_active', methods: ['PATCH'])]
    public function togglePortalUserActive(int $id, Request $request): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('active', $payload)) {
            return $this->json(['error' => 'Champ active manquant'], 400);
        }

        $result = $this->patientService->togglePatientPortalAccount($id, (bool) $payload['active']);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

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

    #[Route('/api/patient/{id}/antecedents', name: 'api_patient_antecedent_add', methods: ['POST'])]
    public function addAntecedent(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $result = $this->patientService->addAntecedent($id, $payload);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/patient/{id}/antecedents/{antecedentId}', name: 'api_patient_antecedent_delete', methods: ['DELETE'])]
    public function deleteAntecedent(int $id, int $antecedentId): JsonResponse
    {
        $result = $this->patientService->deleteAntecedent($id, $antecedentId);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/patient/{id}/allergies', name: 'api_patient_allergy_add', methods: ['POST'])]
    public function addAllergy(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $result = $this->patientService->addAllergy($id, $payload);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/patient/{id}/allergies/{allergyId}', name: 'api_patient_allergy_delete', methods: ['DELETE'])]
    public function deleteAllergy(int $id, int $allergyId): JsonResponse
    {
        $result = $this->patientService->deleteAllergy($id, $allergyId);

        if (isset($result['error'])) {
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

    #[Route('/api/prints/patient/{id}/dossier', name: 'api_print_patient_dossier_data', methods: ['GET'])]
    public function getPrintDossierData(int $id): JsonResponse
    {
        $patient = $this->patientService->getPrintInfosPersoData($id);
        if (!$patient) {
            return $this->json(['error' => 'Patient non trouvé'], 404);
        }

        return $this->json(['patient' => $patient]);
    }

    #[Route('/api/prints/patient/{patientId}/fiche/{ficheId}', name: 'api_print_patient_fiche_data', methods: ['GET'])]
    public function getPrintFicheData(int $patientId, int $ficheId): JsonResponse
    {
        $context = $this->patientService->getPrintFicheData($patientId, $ficheId);
        if (!$context) {
            return $this->json(['error' => 'Fiche introuvable pour ce patient.'], 404);
        }

        return $this->json($context);
    }

    private function denyPortalUserManagementIfUnauthorized(): ?JsonResponse
    {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_RECEPTION')
            && !$this->isGranted('ROLE_RECEPTIONNISTE')
        ) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        return null;
    }
}
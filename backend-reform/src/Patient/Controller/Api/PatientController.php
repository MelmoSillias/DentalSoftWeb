<?php

namespace App\Patient\Controller\Api;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Application\Command\AddAllergy\AddAllergyCommand;
use App\Patient\Application\Command\AddAntecedent\AddAntecedentCommand;
use App\Patient\Application\Command\AddArchiveFile\AddArchiveFileCommand;
use App\Patient\Application\Command\CreatePatient\CreatePatientCommand;
use App\Patient\Application\Command\CreatePatientConsultation\CreatePatientConsultationCommand;
use App\Patient\Application\Command\CreatePatientPortalAccount\CreatePatientPortalAccountCommand;
use App\Patient\Application\Command\CreatePatientRdv\CreatePatientRdvCommand;
use App\Patient\Application\Command\DeleteAllergy\DeleteAllergyCommand;
use App\Patient\Application\Command\DeleteAntecedent\DeleteAntecedentCommand;
use App\Patient\Application\Command\RemoveArchiveFile\RemoveArchiveFileCommand;
use App\Patient\Application\Command\ResetPatientPortalPassword\ResetPatientPortalPasswordCommand;
use App\Patient\Application\Command\RestorePatient\RestorePatientCommand;
use App\Patient\Application\Command\SoftDeletePatient\SoftDeletePatientCommand;
use App\Patient\Application\Command\TogglePatientPortalAccount\TogglePatientPortalAccountCommand;
use App\Patient\Application\Command\UpdatePatient\UpdatePatientCommand;
use App\Patient\Application\Command\UpdatePatientDossier\UpdatePatientDossierCommand;
use App\Patient\Application\Query\CheckActiveConsultation\CheckActiveConsultationQuery;
use App\Patient\Application\Query\GetPatientDetails\GetPatientDetailsQuery;
use App\Patient\Application\Query\GetPatientDossier\GetPatientDossierQuery;
use App\Patient\Application\Query\GetPatientOverviewStats\GetPatientOverviewStatsQuery;
use App\Patient\Application\Query\GetPatientPortalAccount\GetPatientPortalAccountQuery;
use App\Patient\Application\Query\GetPrintFiche\GetPrintFicheQuery;
use App\Patient\Application\Query\GetPrintFicheData\GetPrintFicheDataQuery;
use App\Patient\Application\Query\GetPrintInfosPerso\GetPrintInfosPersoQuery;
use App\Patient\Application\Query\GetPrintInfosPersoData\GetPrintInfosPersoDataQuery;
use App\Patient\Application\Query\ListDeletedPatients\ListDeletedPatientsQuery;
use App\Patient\Application\Query\ListPatientConsultations\ListPatientConsultationsQuery;
use App\Patient\Application\Query\ListPatientsPaginated\ListPatientsPaginatedQuery;
use App\Patient\Application\Query\SearchPatients\SearchPatientsQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    #[Route('/api/patients', name: 'api_patients', methods: ['GET'])]
    public function getPatients(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $query = trim((string) ($request->query->get('q') ?? $request->query->get('term') ?? ''));
        $query = $query !== '' ? $query : null;
        $sortField = $request->query->get('sortField');
        $sortOrder = $request->query->get('sortOrder');

        $paginated = $request->query->has('page') || $request->query->has('limit');

        $result = $this->queryBus->ask(new ListPatientsPaginatedQuery(
            paginated: $paginated,
            page: $page,
            limit: $limit,
            query: $query,
            sortField: $sortField,
            sortOrder: $sortOrder,
        ));

        return $this->json($result);
    }

    #[Route('/api/patients/overview-stats', name: 'api_patients_overview_stats', methods: ['GET'])]
    public function getPatientsOverviewStats(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetPatientOverviewStatsQuery()));
    }

    #[Route('/api/patients/medecin/overview-stats', name: 'api_patients_medecin_overview_stats', methods: ['GET'])]
    public function getPatientsOverviewStatsByMedecin(): JsonResponse
    {
        $result = $this->queryBus->ask(new GetPatientOverviewStatsQuery(
            user: $this->getUser(),
            medecinOnly: true,
        ));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/patients/medecin', name: 'api_patients_by_medecin', methods: ['GET'])]
    public function getPatientsByMedecin(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $query = trim((string) ($request->query->get('q') ?? $request->query->get('term') ?? ''));
        $query = $query !== '' ? $query : null;
        $sortField = $request->query->get('sortField');
        $sortOrder = $request->query->get('sortOrder');

        $paginated = $request->query->has('page') || $request->query->has('limit');

        $result = $this->queryBus->ask(new ListPatientsPaginatedQuery(
            user: $this->getUser(),
            medecinOnly: true,
            paginated: $paginated,
            page: $page,
            limit: $limit,
            query: $query,
            sortField: $sortField,
            sortOrder: $sortOrder,
        ));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/patient/add', name: 'api_patient_add', methods: ['POST'])]
    public function addPatient(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->commandBus->dispatch(new CreatePatientCommand(
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user->getId() : null,
        ));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Patient ajouté avec succès', 'patientId' => $result['patientId']], $result['status'] ?? 201);
    }

    #[Route('/api/patient/{id}/update', name: 'api_patient_update', methods: ['POST'])]
    public function updatePatient(int $id, Request $request): JsonResponse
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        $data = str_contains($contentType, 'application/json')
            ? (json_decode($request->getContent(), true) ?? [])
            : $request->request->all();
        if (!is_array($data)) {
            $data = [];
        }
        $file = $request->files->get('photo');
        
        // Récupération des nouveaux fichiers archivés
        $uploadedArchiveFiles = $request->files->get('archiveFiles', []);
        if (!is_array($uploadedArchiveFiles)) {
            $uploadedArchiveFiles = [];
        }
        
        // Récupération de la liste des fichiers déjà existants (envoyée en JSON)
        $existingArchiveFiles = [];
        if ($request->request->has('archiveFiles')) {
            $existingArchiveFiles = json_decode($request->request->get('archiveFiles', '[]'), true);
            if (!is_array($existingArchiveFiles)) {
                return $this->json(['message' => 'Le champ archiveFiles doit être un tableau JSON'], 400);
            }
        }

        $result = $this->commandBus->dispatch(new UpdatePatientCommand(
            $id,
            $data,
            $file,
            dirname(__DIR__, 4) . '/public/uploads',
            $uploadedArchiveFiles,
            $existingArchiveFiles,
        ));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/api/patient/{id}/archive-file', name: 'api_patient_archive_file_add', methods: ['POST'])]
    public function addArchiveFile(int $id, Request $request): JsonResponse
    {
        $name = $request->request->get('name');
        $file = $request->files->get('file');

        if (!$name || !$file) {
            return $this->json(['message' => 'Nom et fichier requis'], 400);
        }

        $result = $this->commandBus->dispatch(new AddArchiveFileCommand(
            $id,
            (string) $name,
            $file,
            dirname(__DIR__, 4) . '/public/uploads',
        ));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/patient/{id}/archive-file', name: 'api_patient_archive_file_remove', methods: ['DELETE'])]
    public function removeArchiveFile(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $fileUrl = $data['url'] ?? null;

        if (!$fileUrl) {
            return $this->json(['message' => 'URL du fichier manquante'], 400);
        }

        $result = $this->commandBus->dispatch(new RemoveArchiveFileCommand($id, (string) $fileUrl));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }



    #[Route('/api/patient/{id}/consultation-en-cours', name: 'api_consultation_check_active', methods: ['GET'])]
    public function checkConsultationEnCours(int $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new CheckActiveConsultationQuery($id)));
    }

    #[Route('/api/patients/search', name: 'api_patients_search', methods: ['GET'])]
    public function searchPatients(Request $request): JsonResponse
    {
        $term = $request->query->get('q') ?? $request->query->get('term', '');
        $limit = (int) $request->query->get('limit', 20);
        $results = $this->queryBus->ask(new SearchPatientsQuery((string) $term, $limit));
        return $this->json(['results' => $results]);
    }

    #[Route('/api/patients/trash', name: 'api_patients_trash', methods: ['GET'])]
    public function getPatientsTrash(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $query = trim((string) ($request->query->get('q') ?? ''));
        $query = $query !== '' ? $query : null;

        $result = $this->queryBus->ask(new ListDeletedPatientsQuery($page, $limit, $query));

        return $this->json($result);
    }

    #[Route('/api/patient/{id}', name: 'api_patient_soft_delete', methods: ['DELETE'])]
    public function softDeletePatient(int $id): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->commandBus->dispatch(new SoftDeletePatientCommand(
            $id,
            $user instanceof User ? $user->getId() : null,
        ));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 200);
    }

    #[Route('/api/patient/{id}/restore', name: 'api_patient_restore', methods: ['PATCH'])]
    public function restorePatient(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new RestorePatientCommand($id));

        if (isset($result['error'])) {
            return $this->json(['message' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 200);
    }

    #[Route('/api/patient/{id}', name: 'api_patient_details', methods: ['GET'])]
    public function getPatientDetails(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetPatientDetailsQuery($id));

        if ($data === null) {
            return $this->json(['message' => 'Patient non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($data);
    }

    #[Route('/api/patient/{patientId}/consultation/create', name: 'api_consultation_create', methods: ['POST'])]
    public function createConsultation(int $patientId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $user = $this->getUser();
        $result = $this->commandBus->dispatch(new CreatePatientConsultationCommand(
            $patientId,
            $payload,
            $user instanceof User ? $user : null,
        ));

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
        $result = $this->commandBus->dispatch(new CreatePatientRdvCommand(
            json_decode($request->getContent(), true) ?? [],
            $user instanceof User ? $user : null,
        ));

        if (isset($result['error'])) {
            return $this->json(['success' => false, 'error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json([
            'success' => true,
            'rdv_id' => $result['rdv_id'],
            'sms_queued_count' => $result['smsQueuedCount'] ?? 0,
        ], $result['status'] ?? 201);
    }

    #[Route('/api/patient/{id}/dossier', name: 'api_patient_dossier_get', methods: ['GET'])]
    public function getDossier(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetPatientDossierQuery($id));

        if ($data === null) {
            return $this->json(['error' => 'Patient introuvable'], 404);
        }

        return $this->json($data);
    }

    #[Route('/api/patient/{id}/consultations', name: 'api_patient_consultations', methods: ['GET'])]
    public function getPatientConsultations(int $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListPatientConsultationsQuery($id)));
    }

    #[Route('/api/patient/{id}/portal-user', name: 'api_patient_portal_user_get', methods: ['GET'])]
    public function getPortalUser(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->queryBus->ask(new GetPatientPortalAccountQuery($id));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/portal-user/create', name: 'api_patient_portal_user_create', methods: ['POST'])]
    public function createPortalUser(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->commandBus->dispatch(new CreatePatientPortalAccountCommand($id));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/portal-user/reset-password', name: 'api_patient_portal_user_reset_password', methods: ['POST'])]
    public function resetPortalUserPassword(int $id): JsonResponse
    {
        if ($denied = $this->denyPortalUserManagementIfUnauthorized()) {
            return $denied;
        }

        $result = $this->commandBus->dispatch(new ResetPatientPortalPasswordCommand($id));
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

        $result = $this->commandBus->dispatch(new TogglePatientPortalAccountCommand($id, (bool) $payload['active']));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/api/patient/{id}/dossier/update', name: 'api_patient_dossier_update', methods: ['PUT'])]
    public function updateDossier(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $result = $this->commandBus->dispatch(new UpdatePatientDossierCommand($id, $payload ?? []));

        if (!empty($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/patient/{id}/antecedents', name: 'api_patient_antecedent_add', methods: ['POST'])]
    public function addAntecedent(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $result = $this->commandBus->dispatch(new AddAntecedentCommand($id, $payload));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/patient/{id}/antecedents/{antecedentId}', name: 'api_patient_antecedent_delete', methods: ['DELETE'])]
    public function deleteAntecedent(int $id, int $antecedentId): JsonResponse
    {
        $result = $this->commandBus->dispatch(new DeleteAntecedentCommand($id, $antecedentId));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/patient/{id}/allergies', name: 'api_patient_allergy_add', methods: ['POST'])]
    public function addAllergy(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $result = $this->commandBus->dispatch(new AddAllergyCommand($id, $payload));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/patient/{id}/allergies/{allergyId}', name: 'api_patient_allergy_delete', methods: ['DELETE'])]
    public function deleteAllergy(int $id, int $allergyId): JsonResponse
    {
        $result = $this->commandBus->dispatch(new DeleteAllergyCommand($id, $allergyId));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/patient/{id}/dossier/print/infosperso', name: 'patient_print_infos_perso', methods: ['GET'])]
    public function print(int $id): Response
    {
        $patient = $this->queryBus->ask(new GetPrintInfosPersoQuery($id));
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
        $context = $this->queryBus->ask(new GetPrintFicheQuery($patientId, $ficheId));

        if (!$context) {
            throw $this->createNotFoundException('Fiche introuvable pour ce patient.');
        }

        return $this->render('pages_bases/fiche_print.html.twig', $context);
    }

    #[Route('/api/prints/patient/{id}/dossier', name: 'api_print_patient_dossier_data', methods: ['GET'])]
    public function getPrintDossierData(int $id): JsonResponse
    {
        $patient = $this->queryBus->ask(new GetPrintInfosPersoDataQuery($id));
        if (!$patient) {
            return $this->json(['error' => 'Patient non trouvé'], 404);
        }

        return $this->json(['patient' => $patient]);
    }

    #[Route('/api/prints/patient/{patientId}/fiche/{ficheId}', name: 'api_print_patient_fiche_data', methods: ['GET'])]
    public function getPrintFicheData(int $patientId, int $ficheId): JsonResponse
    {
        $context = $this->queryBus->ask(new GetPrintFicheDataQuery($patientId, $ficheId));
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

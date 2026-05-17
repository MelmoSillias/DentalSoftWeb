<?php

namespace App\Patient\Controller\ApiPortalPatient;

use App\IdentityAccess\Entity\User;
use App\Patient\Entity\Patient;
use App\Patient\Service\PatientPortalService;
use App\Settings\Service\GlobalSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/portal-patient/me', name: 'api_patient_me_')]
#[IsGranted('ROLE_PATIENT')]
final class PatientPortalController extends AbstractController
{
    public function __construct(
        private readonly PatientPortalService $patientPortalService,
        private readonly GlobalSettingsService $globalSettingsService,
    ) {
    }

    private function ensurePortalEnabled(): ?JsonResponse
    {
        if ($this->globalSettingsService->isPatientPortalEnabled()) {
            return null;
        }

        return $this->json([
            'error' => $this->globalSettingsService->getPatientPortalClosedMessage(),
            'patientPortalEnabled' => false,
        ], 403);
    }

    #[Route('/consultations', name: 'consultations', methods: ['GET'])]
    public function consultations(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildConsultationsPayload($patient));
    }

    #[Route('/devis-factures', name: 'devis_factures', methods: ['GET'])]
    public function devisFactures(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildDevisFacturesPayload($patient));
    }

    #[Route('/paiements', name: 'paiements', methods: ['GET'])]
    public function paiements(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildPaiementsPayload($patient));
    }

    #[Route('/paiements/{id}/recu', name: 'paiement_recu', methods: ['GET'])]
    public function recu(int $id): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $payload = $this->patientPortalService->buildReceiptPayload($patient, $id);
        if ($payload === null) {
            return $this->json(['error' => 'Recu introuvable'], 404);
        }

        return $this->json($payload);
    }

    #[Route('/rdvs', name: 'rdvs', methods: ['GET'])]
    public function rdvs(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildRdvsPayload($patient));
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifie'], 401);
        }

        return $this->json($this->patientPortalService->buildNotificationsPayload($user));
    }

    #[Route('/notifications/mercure', name: 'notifications_mercure', methods: ['GET'])]
    public function notificationsMercure(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifie'], 401);
        }

        $subscription = $this->patientPortalService->buildMercureSubscription($user);
        if ($subscription === null) {
            return $this->json(['error' => 'Impossible de generer la souscription Mercure'], 400);
        }

        return $this->json($subscription);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildDashboardPayload($patient));
    }

    #[Route('/profil', name: 'profil', methods: ['GET'])]
    public function profil(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildProfilePayload($patient));
    }

    #[Route('/consultations/{id}', name: 'consultation_detail', methods: ['GET'])]
    public function consultationDetail(int $id): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $detail = $this->patientPortalService->buildConsultationDetailPayload($patient, $id);
        if ($detail === null) {
            return $this->json(['error' => 'Consultation introuvable'], 404);
        }

        return $this->json($detail);
    }

    #[Route('/devis-factures/{id}', name: 'document_detail', methods: ['GET'])]
    public function documentDetail(int $id): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $detail = $this->patientPortalService->buildDocumentDetailPayload($patient, $id);
        if ($detail === null) {
            return $this->json(['error' => 'Document introuvable'], 404);
        }

        return $this->json($detail);
    }
}

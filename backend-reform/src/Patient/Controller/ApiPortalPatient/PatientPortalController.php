<?php

namespace App\Patient\Controller\ApiPortalPatient;

use App\IdentityAccess\Entity\User;
use App\Patient\Entity\Patient;
use App\Patient\Service\PatientPortalService;
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
    ) {
    }

    #[Route('/consultations', name: 'consultations', methods: ['GET'])]
    public function consultations(): JsonResponse
    {
        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildConsultationsPayload($patient));
    }

    #[Route('/devis-factures', name: 'devis_factures', methods: ['GET'])]
    public function devisFactures(): JsonResponse
    {
        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildDevisFacturesPayload($patient));
    }

    #[Route('/paiements', name: 'paiements', methods: ['GET'])]
    public function paiements(): JsonResponse
    {
        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildPaiementsPayload($patient));
    }

    #[Route('/paiements/{id}/recu', name: 'paiement_recu', methods: ['GET'])]
    public function recu(int $id): JsonResponse
    {
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
        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildRdvsPayload($patient));
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifie'], 401);
        }

        return $this->json($this->patientPortalService->buildNotificationsPayload($user));
    }

    #[Route('/notifications/mercure', name: 'notifications_mercure', methods: ['GET'])]
    public function notificationsMercure(): JsonResponse
    {
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
        $patient = $this->patientPortalService->resolveAuthenticatedPatient($this->getUser());
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        return $this->json($this->patientPortalService->buildDashboardPayload($patient));
    }
}

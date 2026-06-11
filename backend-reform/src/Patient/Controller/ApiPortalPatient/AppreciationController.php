<?php

namespace App\Patient\Controller\ApiPortalPatient;

use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ConsultationRepository;
use App\IdentityAccess\Entity\User;
use App\Patient\Entity\Appreciation;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\Patient\Service\AppreciationService;
use App\Settings\Service\GlobalSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_appreciation_')]
final class AppreciationController extends AbstractController
{
    public function __construct(
        private readonly AppreciationService $appreciationService,
        private readonly PatientRepository $patientRepository,
        private readonly ConsultationRepository $consultationRepository,
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

    #[Route('/appreciations/public', name: 'public_list', methods: ['GET'])]
    public function listPublicAppreciations(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 50);
        $items = array_map(
            fn(Appreciation $appreciation): array => $this->mapPublicAppreciation($appreciation),
            $this->appreciationService->listPublishedForPublic($limit)
        );

        return $this->json([
            'stats' => $this->appreciationService->getPublicStats(),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/appreciations/anonymous', name: 'anonymous_create', methods: ['POST'])]
    public function createAnonymous(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $appreciation = $this->appreciationService->createAnonymous($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json(['item' => $this->mapAppreciation($appreciation)], 201);
    }

    #[Route('/portal-patient/me/appreciations', name: 'patient_create', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function createPatientAppreciation(Request $request): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $appreciation = $this->appreciationService->createForPatient($patient, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json([
            'patient' => ['id' => $patient->getId(), 'nom' => $patient->getFullName()],
            'item' => $this->mapAppreciation($appreciation),
        ], 201);
    }

    #[Route('/portal-patient/me/consultations/{consultationId}/appreciation', name: 'consultation_create', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function createConsultationAppreciation(int $consultationId, Request $request): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $consultation = $this->consultationRepository->find($consultationId);
        if (!$consultation instanceof Consultation) {
            return $this->json(['error' => 'Consultation introuvable'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $appreciation = $this->appreciationService->createForConsultation($patient, $consultation, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        return $this->json([
            'patient' => ['id' => $patient->getId(), 'nom' => $patient->getFullName()],
            'item' => $this->mapAppreciation($appreciation),
        ], 201);
    }

    #[Route('/portal-patient/me/appreciations', name: 'patient_list', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function listPatientAppreciations(): JsonResponse
    {
        if ($blocked = $this->ensurePortalEnabled()) {
            return $blocked;
        }

        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $items = array_map(
            fn(Appreciation $appreciation): array => $this->mapAppreciation($appreciation),
            $this->appreciationService->listByPatient($patient)
        );

        return $this->json([
            'patient' => ['id' => $patient->getId(), 'nom' => $patient->getFullName()],
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/administration/appreciations', name: 'admin_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listAdminAppreciations(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 200);
        $items = array_map(
            fn(Appreciation $appreciation): array => $this->mapAppreciation($appreciation),
            $this->appreciationService->listForAdmin($limit)
        );

        return $this->json([
            'stats' => $this->appreciationService->getAdminStats(),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/administration/appreciations/{id}/publish', name: 'admin_publish', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setAdminAppreciationPublished(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('isPublished', $payload)) {
            return $this->json(['error' => 'Le champ isPublished est requis.'], 400);
        }

        try {
            $appreciation = $this->appreciationService->setPublished($id, (bool) $payload['isPublished']);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 404);
        }

        return $this->json(['item' => $this->mapAppreciation($appreciation)]);
    }

    #[Route('/administration/appreciations/{id}', name: 'admin_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAdminAppreciation(int $id): JsonResponse
    {
        try {
            $this->appreciationService->delete($id);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 404);
        }

        return $this->json(['success' => true]);
    }

    private function mapPublicAppreciation(Appreciation $appreciation): array
    {
        $displayName = null;
        if (!$appreciation->isAnonymous()) {
            $displayName = $appreciation->getAuthorName()
                ?: $appreciation->getPatient()?->getFullName();
        } elseif ($appreciation->getAuthorName()) {
            $displayName = $appreciation->getAuthorName();
        }

        return [
            'id' => $appreciation->getId(),
            'rating' => $appreciation->getRating(),
            'comment' => $appreciation->getComment(),
            'isAnonymous' => $appreciation->isAnonymous(),
            'authorName' => $displayName,
            'createdAt' => $appreciation->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function mapAppreciation(Appreciation $appreciation): array
    {
        return [
            'id' => $appreciation->getId(),
            'rating' => $appreciation->getRating(),
            'comment' => $appreciation->getComment(),
            'isAnonymous' => $appreciation->isAnonymous(),
            'isPublished' => $appreciation->isPublished(),
            'authorName' => $appreciation->isAnonymous() ? null : $appreciation->getAuthorName(),
            'authorEmail' => $appreciation->isAnonymous() ? null : $appreciation->getAuthorEmail(),
            'patientId' => $appreciation->getPatient()?->getId(),
            'patientName' => $appreciation->getPatient()?->getFullName(),
            'consultationId' => $appreciation->getConsultation()?->getId(),
            'createdAt' => $appreciation->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function resolveAuthenticatedPatient(): ?Patient
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $patient = $user->getPortalPatient();
        if ($patient instanceof Patient) {
            return $patient;
        }

        return $this->patientRepository->findOneBy(['portalUser' => $user]);
    }
}

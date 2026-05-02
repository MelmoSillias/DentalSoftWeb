<?php

namespace App\Scheduling\Controller\Api;

use App\Scheduling\Entity\Rdv;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Scheduling\Service\RdvService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RdvController extends AbstractController
{
    public function __construct(
        private RdvService $rdvService,
        private EmployeRepository $employeRepo,
    ) {
    }

    private function jsonPayload(Request $request): array
    {
        $content = $request->getContent();
        if ($content) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data ?? [];
            }
        }

        return $request->request->all();
    }

    #[Route('/api/rdv/create', name: 'api_rdv_create', methods: ['POST'])]
    public function createRdv(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->rdvService->createRdv(
            $this->jsonPayload($request),
            $user instanceof User ? $user : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/{id}/{action}', name: 'api_rdv_action', methods: ['POST'])]
    public function handleRdvAction(Request $request, Rdv $rdv, string $action): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $user = $this->getUser();

        $result = $this->rdvService->handleAction(
            $rdv,
            $action,
            $payload,
            $user instanceof User ? $user : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/stats', name: 'api_rdv_stats', methods: ['GET'])]
    public function rdvStats(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        $actorMedecin = $isMedecin ? $this->rdvService->getMedecinForUser($actor) : null;
        if ($isMedecin && !$actorMedecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 403);
        }

        $dateStr = $request->query->get('date');
        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');
        $medecinId = $isMedecin ? $actorMedecin?->getId() : $request->query->get('medecin');

        try {
            if ($dateStr) {
                $date = new DateTime($dateStr);
                if ($medecinId) {
                    $medecin = $this->employeRepo->find((int) $medecinId);
                    $data = $medecin ? $this->rdvService->getStatsForMedecinDate($date, $medecin) : ['error' => 'Médecin introuvable', 'status' => 404];
                } else {
                    $data = $this->rdvService->getStatsForDate($date);
                }
            } elseif ($startStr && $endStr) {
                $start = new DateTime($startStr);
                $end = new DateTime($endStr);
                $data = $this->rdvService->getStatsForRange($start, $end);
            } else {
                $data = $this->rdvService->getStatsForDate(new DateTime());
            }
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $status = $data['status'] ?? 200;

        return new JsonResponse($data, $status);
    }

    #[Route('/api/rdvs/stats/{date}', name: 'api_rdvs_stats_by_date', methods: ['GET'])]
    public function rdvStatsByDate(string $date, Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        $actorMedecin = $isMedecin ? $this->rdvService->getMedecinForUser($actor) : null;
        if ($isMedecin && !$actorMedecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 403);
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $isMedecin ? $actorMedecin?->getId() : $request->query->get('medecin');
        if ($medecinId) {
            $medecin = $this->employeRepo->find((int) $medecinId);
            if (!$medecin) {
                return new JsonResponse(['error' => 'Médecin introuvable'], 404);
            }
            $data = $this->rdvService->getStatsForMedecinDate($dateObj, $medecin);
        } else {
            $data = $this->rdvService->getStatsForDate($dateObj);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs/{date}', name: 'api_rdvs_by_date', methods: ['GET'])]
    public function listRdvsByDate(Request $request, string $date): JsonResponse
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        $actorMedecin = $isMedecin ? $this->rdvService->getMedecinForUser($actor) : null;
        if ($isMedecin && !$actorMedecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 403);
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $isMedecin ? $actorMedecin?->getId() : $request->query->get('medecin');
        $medecin = $medecinId ? $this->employeRepo->find((int) $medecinId) : null;
        if ($medecinId && !$medecin) {
            return new JsonResponse(['error' => 'Médecin introuvable'], 404);
        }
        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->rdvService->listByDate($dateObj, $medecin, $excludeCancelled);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs', name: 'api_rdvs_range', methods: ['GET'])]
    public function listRdvsRange(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        $actorMedecin = $isMedecin ? $this->rdvService->getMedecinForUser($actor) : null;
        if ($isMedecin && !$actorMedecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 403);
        }

        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');

        if (!$startStr || !$endStr) {
            return new JsonResponse(['error' => 'Plage de dates requise'], 400);
        }

        $start = DateTime::createFromFormat('Y-m-d', substr($startStr, 0, 10));
        $end = DateTime::createFromFormat('Y-m-d', substr($endStr, 0, 10));

        if (!$start || !$end) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $isMedecin ? $actorMedecin?->getId() : $request->query->get('medecin');
        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->rdvService->listByRange($start, $end, $medecinId ? (int) $medecinId : null, $excludeCancelled);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs_pending', name: 'api_pending_rdvs_range', methods: ['GET'])]
    public function listPendingRdvsRange(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        $actorMedecin = $isMedecin ? $this->rdvService->getMedecinForUser($actor) : null;
        if ($isMedecin && !$actorMedecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 403);
        }

        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');

        if (!$startStr || !$endStr) {
            return new JsonResponse(['error' => 'Plage de dates requise'], 400);
        }

        $start = DateTime::createFromFormat('Y-m-d', $startStr);
        $end = DateTime::createFromFormat('Y-m-d', $endStr);

        if (!$start || !$end) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $isMedecin ? $actorMedecin?->getId() : $request->query->get('medecin');

        $data = $this->rdvService->listPendingByRange($start, $end, $medecinId ? (int) $medecinId : null);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs/{date}/medecin', name: 'api_rdvs_bymedecin', methods: ['GET'])]
    public function listRdvsForCurrentMedecin(string $date): JsonResponse
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecin = $this->rdvService->getMedecinForUser($this->getUser());
        if (!$medecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 404);
        }

        $data = $this->rdvService->listByDate($dateObj, $medecin);

        return new JsonResponse($data);
    }
}
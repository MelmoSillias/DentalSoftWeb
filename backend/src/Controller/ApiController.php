<?php

namespace App\Controller;

use App\Entity\Rdv;
use App\Repository\EmployeRepository;
use App\Service\AgendaService;
use App\Service\DashboardStatsService;
use App\Service\RdvService;
use App\Service\SalleService;
use App\Service\UserManagementService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private RdvService $rdvService,
        private AgendaService $agendaService,
        private SalleService $salleService,
        private UserManagementService $userService,
        private DashboardStatsService $dashboardStatsService,
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
        $result = $this->rdvService->createRdv($this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/{id}/{action}', name: 'api_rdv_action', methods: ['POST'])]
    public function handleRdvAction(Request $request, Rdv $rdv, string $action): JsonResponse
    {
        $result = $this->rdvService->handleAction($rdv, $action, $this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/stats', name: 'api_rdv_stats', methods: ['GET'])]
    public function rdvStats(Request $request): JsonResponse
    {
        $dateStr = $request->query->get('date');
        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');
        $medecinId = $request->query->get('medecin');

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

        $status = $data['status'] ?? 200;

        return new JsonResponse($data, $status);
    }

    #[Route('/api/rdvs/stats/{date}', name: 'api_rdvs_stats_by_date', methods: ['GET'])]
    public function rdvStatsByDate(string $date, Request $request): JsonResponse
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $request->query->get('medecin');
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
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $request->query->get('medecin');
        $medecin = $medecinId ? $this->employeRepo->find((int) $medecinId) : null;
        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->rdvService->listByDate($dateObj, $medecin, $excludeCancelled);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs', name: 'api_rdvs_range', methods: ['GET'])]
    public function listRdvsRange(Request $request): JsonResponse
    {
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

        $medecinId = $request->query->get('medecin');
        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->rdvService->listByRange($start, $end, $medecinId ? (int) $medecinId : null, $excludeCancelled);

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs_pending', name: 'api_pending_rdvs_range', methods: ['GET'])]
    public function listPendingRdvsRange(Request $request): JsonResponse
    {
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

        $medecinId = $request->query->get('medecin');

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

    #[Route('/api/events/all', name: 'api_events_all', methods: ['GET'])]
    public function listEvents(): JsonResponse
    {
        return new JsonResponse($this->agendaService->listBookings());
    }

    #[Route('/api/event/createBooking', name: 'api_event_create_booking', methods: ['POST', 'GET'])]
    public function createBooking(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $result = $this->agendaService->createBooking($payload);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/event/{id}/delete', name: 'api_event_delete', methods: ['POST', 'DELETE'])]
    public function deleteBooking(int $id): JsonResponse
    {
        $result = $this->agendaService->deleteBooking($id);
        $status = $result['status'] ?? (isset($result['error']) ? 404 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/event/{id}/validate', name: 'api_event_validate', methods: ['POST'])]
    public function validateBooking(int $id): JsonResponse
    {
        $result = $this->agendaService->validateBooking($id);
        $status = $result['status'] ?? (isset($result['error']) ? 404 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/salles', name: 'api_salles', methods: ['GET'])]
    public function getSalles(): JsonResponse
    {
        $salles = $this->salleService->list();

        $data = array_map(static function ($salle) {
            return [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'description' => $salle->getDescription(),
            ];
        }, $salles);

        return new JsonResponse($data);
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getMedecins(): JsonResponse
    {
        $medecins = $this->employeRepo->findBy(['type' => 'medecin']);

        $data = array_map(static function ($medecin) {
            return [
                'id' => $medecin->getId(),
                'nom' => $medecin->getNom(),
                'prenom' => $medecin->getPrenom(),
                'fullName' => method_exists($medecin, 'getFullName') ? $medecin->getFullName() : trim($medecin->getNom() . ' ' . $medecin->getPrenom()),
            ];
        }, $medecins);

        return new JsonResponse($data);
    }

    #[Route('/api/users/create', name: 'api_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $result = $this->userService->createUser($this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/update', name: 'api_users_update', methods: ['POST'])]
    public function updateUser(Request $request): JsonResponse
    {
        $result = $this->userService->updateUser($this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/reset_password', name: 'api_users_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $result = $this->userService->resetPassword($this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/delete', name: 'api_users_delete', methods: ['POST'])]
    public function deleteUser(Request $request): JsonResponse
    {
        $result = $this->userService->deleteUser($this->jsonPayload($request));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/reports', name: 'api_reports_data', methods: ['GET'])]
    public function reports(Request $request): JsonResponse
    {
        $period = $request->query->get('period', 'month');
        $employeeId = $request->query->get('employeeId');
        $customStart = $request->query->get('start');
        $customEnd = $request->query->get('end');

        $data = $this->dashboardStatsService->getReportsData($period, $customStart, $customEnd, $employeeId);

        return new JsonResponse($data);
    }
}

<?php

namespace App\Scheduling\Controller\Api;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use App\Scheduling\Application\Command\CreateRdv\CreateRdvCommand;
use App\Scheduling\Application\Command\HandleRdvAction\HandleRdvActionCommand;
use App\Scheduling\Application\Query\GetRdvStats\GetRdvStatsQuery;
use App\Scheduling\Application\Query\ListRdvs\ListRdvsQuery;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RdvController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
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

    /**
     * @return array{0: ?int, 1: ?JsonResponse}
     */
    private function resolveMedecinScope(): array
    {
        $actor = $this->getUser();
        $isMedecin = in_array('ROLE_MEDECIN', $actor?->getRoles() ?? [], true);
        if (!$isMedecin) {
            return [null, null];
        }

        $actorMedecin = $actor ? $this->employeRepo->findOneBy(['user' => $actor]) : null;
        if (!$actorMedecin) {
            return [null, new JsonResponse(['error' => 'Aucun médecin associé'], 403)];
        }

        return [$actorMedecin->getId(), null];
    }

    #[Route('/api/rdv/create', name: 'api_rdv_create', methods: ['POST'])]
    public function createRdv(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $result = $this->commandBus->dispatch(new CreateRdvCommand(
            $this->jsonPayload($request),
            $user instanceof User ? $user : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/{id}/{action}', name: 'api_rdv_action', methods: ['POST'])]
    public function handleRdvAction(Request $request, Rdv $rdv, string $action): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $user = $this->getUser();

        $result = $this->commandBus->dispatch(new HandleRdvActionCommand(
            (int) $rdv->getId(),
            $action,
            $payload,
            $user instanceof User ? $user : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/rdv/stats', name: 'api_rdv_stats', methods: ['GET'])]
    public function rdvStats(Request $request): JsonResponse
    {
        [$actorMedecinId, $denied] = $this->resolveMedecinScope();
        if ($denied) {
            return $denied;
        }

        $dateStr = $request->query->get('date');
        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');
        $medecinId = $actorMedecinId ?? ($request->query->get('medecin') !== null
            ? (int) $request->query->get('medecin')
            : null);

        if ($medecinId !== null && $actorMedecinId === null && !$this->employeRepo->find($medecinId)) {
            return new JsonResponse(['error' => 'Médecin introuvable', 'status' => 404], 404);
        }

        $data = $this->queryBus->ask(new GetRdvStatsQuery(
            date: is_string($dateStr) ? $dateStr : null,
            start: is_string($startStr) ? $startStr : null,
            end: is_string($endStr) ? $endStr : null,
            medecinId: $medecinId,
        ));
        $status = $data['status'] ?? 200;

        return new JsonResponse($data, $status);
    }

    #[Route('/api/rdvs/stats/{date}', name: 'api_rdvs_stats_by_date', methods: ['GET'])]
    public function rdvStatsByDate(string $date, Request $request): JsonResponse
    {
        [$actorMedecinId, $denied] = $this->resolveMedecinScope();
        if ($denied) {
            return $denied;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $actorMedecinId ?? ($request->query->get('medecin') !== null
            ? (int) $request->query->get('medecin')
            : null);
        if ($medecinId !== null && $actorMedecinId === null && !$this->employeRepo->find($medecinId)) {
            return new JsonResponse(['error' => 'Médecin introuvable'], 404);
        }

        $data = $this->queryBus->ask(new GetRdvStatsQuery(
            date: $date,
            medecinId: $medecinId,
        ));

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs/{date}', name: 'api_rdvs_by_date', methods: ['GET'])]
    public function listRdvsByDate(Request $request, string $date): JsonResponse
    {
        [$actorMedecinId, $denied] = $this->resolveMedecinScope();
        if ($denied) {
            return $denied;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecinId = $actorMedecinId ?? ($request->query->get('medecin') !== null
            ? (int) $request->query->get('medecin')
            : null);
        if ($medecinId !== null && $actorMedecinId === null && !$this->employeRepo->find($medecinId)) {
            return new JsonResponse(['error' => 'Médecin introuvable'], 404);
        }

        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->queryBus->ask(new ListRdvsQuery(
            mode: ListRdvsQuery::MODE_DATE,
            date: $date,
            medecinId: $medecinId,
            excludeCancelled: $excludeCancelled,
        ));

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs', name: 'api_rdvs_range', methods: ['GET'])]
    public function listRdvsRange(Request $request): JsonResponse
    {
        [$actorMedecinId, $denied] = $this->resolveMedecinScope();
        if ($denied) {
            return $denied;
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

        $medecinId = $actorMedecinId ?? ($request->query->get('medecin') !== null
            ? (int) $request->query->get('medecin')
            : null);
        $excludeCancelled = in_array('ROLE_RECEPTIONNISTE', $this->getUser()?->getRoles() ?? [], true);

        $data = $this->queryBus->ask(new ListRdvsQuery(
            mode: ListRdvsQuery::MODE_RANGE,
            start: (string) $startStr,
            end: (string) $endStr,
            medecinId: $medecinId,
            excludeCancelled: $excludeCancelled,
        ));

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs_pending', name: 'api_pending_rdvs_range', methods: ['GET'])]
    public function listPendingRdvsRange(Request $request): JsonResponse
    {
        [$actorMedecinId, $denied] = $this->resolveMedecinScope();
        if ($denied) {
            return $denied;
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

        $medecinId = $actorMedecinId ?? ($request->query->get('medecin') !== null
            ? (int) $request->query->get('medecin')
            : null);

        $data = $this->queryBus->ask(new ListRdvsQuery(
            mode: ListRdvsQuery::MODE_PENDING,
            start: (string) $startStr,
            end: (string) $endStr,
            medecinId: $medecinId,
        ));

        return new JsonResponse($data);
    }

    #[Route('/api/rdvs/{date}/medecin', name: 'api_rdvs_bymedecin', methods: ['GET'])]
    public function listRdvsForCurrentMedecin(string $date): JsonResponse
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return new JsonResponse(['error' => 'Format de date invalide'], 400);
        }

        $medecin = $this->getUser()
            ? $this->employeRepo->findOneBy(['user' => $this->getUser()])
            : null;
        if (!$medecin) {
            return new JsonResponse(['error' => 'Aucun médecin associé'], 404);
        }

        $data = $this->queryBus->ask(new ListRdvsQuery(
            mode: ListRdvsQuery::MODE_DATE,
            date: $date,
            medecinId: $medecin->getId(),
        ));

        return new JsonResponse($data);
    }
}

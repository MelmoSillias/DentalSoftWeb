<?php

namespace App\Controller\Api;

use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboardService,
        private EmployeRepository $employeRepo,
    ) {
    }

    #[Route('/{role}/{type}', name: 'data', methods: ['GET'])]
    public function data(string $role, string $type, Request $request): JsonResponse
    {
        $role = strtolower($role);
        $type = strtolower($type);

        if (!in_array($type, ['cards', 'carousels', 'tabs'], true)) {
            return $this->json(['error' => 'type_invalide'], 400);
        }

        [$from, $to, $error] = $this->parseRange($request);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        return match ($role) {
            'admin' => $this->handleAdmin($type, $from, $to),
            'medecin' => $this->handleMedecin($type, $from, $to),
            'reception' => $this->handleReception($type, $from, $to),
            default => $this->json(['error' => 'role_invalide'], 400),
        };
    }

    private function handleAdmin(string $type, \DateTimeImmutable $from, \DateTimeImmutable $to): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'access_denied'], 403);
        }

        $data = match ($type) {
            'cards' => $this->dashboardService->getAdminCards($from, $to),
            'carousels' => $this->dashboardService->getAdminCarousels($from, $to),
            'tabs' => $this->dashboardService->getAdminTabs($from, $to),
        };

        return $this->json($data);
    }

    private function handleMedecin(string $type, \DateTimeImmutable $from, \DateTimeImmutable $to): JsonResponse
    {
        if (!$this->isGranted('ROLE_MEDECIN')) {
            return $this->json(['error' => 'access_denied'], 403);
        }

        $user = $this->getUser();
        /** @var Employe|null $medecin */
        $medecin = $user ? $this->employeRepo->findOneBy(['user' => $user]) : null;
        if (!$medecin) {
            return $this->json(['error' => 'medecin_introuvable'], 404);
        }

        $data = match ($type) {
            'cards' => $this->dashboardService->getMedecinCards($medecin, $from, $to),
            'carousels' => $this->dashboardService->getMedecinCarousels($medecin, $from, $to),
            'tabs' => $this->dashboardService->getMedecinTabs($medecin, $from, $to),
        };

        return $this->json($data);
    }

    private function handleReception(string $type, \DateTimeImmutable $from, \DateTimeImmutable $to): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECEPTION') && !$this->isGranted('ROLE_RECEPTIONNISTE')) {
            return $this->json(['error' => 'access_denied'], 403);
        }

        $data = match ($type) {
            'cards' => $this->dashboardService->getReceptionCards($from, $to),
            'carousels' => $this->dashboardService->getReceptionCarousels($from, $to),
            'tabs' => $this->dashboardService->getReceptionTabs($from, $to),
        };

        return $this->json($data);
    }

    private function parseRange(Request $request): array
    {
        $date = $request->query->get('date');
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        if ($date) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if (!$parsed) {
                return [null, null, 'date_invalide'];
            }
            $start = $parsed->setTime(0, 0, 0);
            $end = $parsed->setTime(23, 59, 59);
            return [$start, $end, null];
        }

        if ($from || $to) {
            $fromDate = $from
                ? \DateTimeImmutable::createFromFormat('Y-m-d', $from)
                : new \DateTimeImmutable('today');
            $toDate = $to
                ? \DateTimeImmutable::createFromFormat('Y-m-d', $to)
                : new \DateTimeImmutable('today');

            if (($from && !$fromDate) || ($to && !$toDate)) {
                return [null, null, 'date_invalide'];
            }

            return [
                $fromDate->setTime(0, 0, 0),
                $toDate->setTime(23, 59, 59),
                null,
            ];
        }

        $today = new \DateTimeImmutable('today');
        return [$today->setTime(0, 0, 0), $today->setTime(23, 59, 59), null];
    }
}

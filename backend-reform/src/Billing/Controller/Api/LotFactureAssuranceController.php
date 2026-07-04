<?php

namespace App\Billing\Controller\Api;

use App\Billing\Service\InsuranceClaimService;
use App\Billing\Service\LotFactureAssuranceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LotFactureAssuranceController extends AbstractController
{
    public function __construct(
        private LotFactureAssuranceService $lotService,
        private InsuranceClaimService $insuranceClaimService,
    ) {
    }

    #[Route('/api/assurances/dashboard', name: 'api_assurances_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json(['data' => $this->lotService->getDashboard()]);
    }

    #[Route('/api/assurances/{code}/lots', name: 'api_assurances_lots_list', methods: ['GET'])]
    public function listLots(string $code, Request $request): JsonResponse
    {
        $statut = $request->query->get('statut');
        $result = $this->lotService->listLots($code, is_string($statut) ? $statut : null);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/{code}/lots', name: 'api_assurances_lots_open', methods: ['POST'])]
    public function openLot(string $code, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        $dateDebut = null;
        $dateFin = null;
        try {
            if (!empty($payload['dateDebut'])) {
                $dateDebut = new \DateTime((string) $payload['dateDebut']);
            }
            if (!empty($payload['dateFin'])) {
                $dateFin = new \DateTime((string) $payload['dateFin']);
            }
        } catch (\Exception) {
            return $this->json(['error' => 'Dates invalides'], 400);
        }

        $result = $this->lotService->openLot(
            $code,
            isset($payload['description']) ? (string) $payload['description'] : null,
            $dateDebut,
            $dateFin,
        );

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error'], 'lotId' => $result['lotId'] ?? null], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/assurances/lots/{id}', name: 'api_assurances_lots_detail', methods: ['GET'])]
    public function getLot(int $id): JsonResponse
    {
        $result = $this->lotService->getLot($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 404);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/send', name: 'api_assurances_lots_send', methods: ['POST'])]
    public function sendLot(int $id): JsonResponse
    {
        $result = $this->lotService->sendLot($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/recover', name: 'api_assurances_lots_recover', methods: ['POST'])]
    public function recoverLot(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $modeId = (int) ($payload['modeId'] ?? 0);

        $date = null;
        if (!empty($payload['date'])) {
            try {
                $date = new \DateTimeImmutable((string) $payload['date']);
            } catch (\Exception) {
                return $this->json(['error' => 'Date invalide'], 400);
            }
        }

        $result = $this->lotService->recoverLot($id, $modeId, $date);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/assurances/lots/{id}/recover/cancel', name: 'api_assurances_lots_recover_cancel', methods: ['PATCH'])]
    public function cancelLotRecovery(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $comment = isset($payload['comment']) ? (string) $payload['comment'] : null;

        $result = $this->lotService->cancelLotRecovery($id, $comment);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/claims', name: 'api_assurances_lots_add_claim', methods: ['POST'])]
    public function addClaimToLot(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $factureId = (int) ($payload['factureId'] ?? $payload['claimId'] ?? 0);

        $result = $this->lotService->addClaimToLot($id, $factureId);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/claims/{factureId}', name: 'api_assurances_lots_remove_claim', methods: ['DELETE'])]
    public function removeClaimFromLot(int $id, int $factureId): JsonResponse
    {
        $result = $this->lotService->removeClaimFromLot($id, $factureId);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/claims/unpaid-patient', name: 'api_assurances_claims_unpaid_patient', methods: ['GET'], priority: 10)]
    public function listClaimsWithUnpaidPatient(): JsonResponse
    {
        return $this->json(['data' => $this->insuranceClaimService->listClaimsWithUnpaidPatient()]);
    }

    #[Route('/api/assurances/claims/{id}', name: 'api_assurances_claim_detail', methods: ['GET'], requirements: ['id' => '\d+'], priority: -10)]
    public function getClaimDetail(int $id): JsonResponse
    {
        $result = $this->insuranceClaimService->getClaimDetail($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 404);
        }

        return $this->json($result);
    }
}

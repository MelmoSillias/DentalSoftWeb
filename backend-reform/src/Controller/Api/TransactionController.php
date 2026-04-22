<?php

namespace App\Controller\Api;

use App\Service\FinanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TransactionController extends AbstractController
{
    public function __construct(
        private FinanceService $financeService,
    ) {}

    #[Route('/api/transactions', name: 'api_transactions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $start = $request->query->get('startDate');
        $end = $request->query->get('endDate');

        if (!$start || !$end) {
            return $this->json(['error' => 'startDate et endDate sont requis.'], 400);
        }

        try {
            $startDate = (new \DateTime($start))->setTime(0, 0, 0);
            $endDate = (new \DateTime($end))->setTime(23, 59, 59);
        } catch (\Exception) {
            return $this->json(['error' => 'Période invalide.'], 400);
        }


        $transactions = $this->financeService->getTransactionsByDateRange($startDate, $endDate);

        return $this->json($transactions);
    }

    #[Route('/api/transaction', name: 'api_transaction_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Données requises'], 400);
        }

        $result = $this->financeService->createTransaction(
            $data['type'],
            (float) $data['montant'],
            $data['description'] ?? null,
            new \DateTime($data['date']),
            (int) $data['modeId'],
            $data['motif'] ?? null,
        );

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Transaction enregistrée avec succès'] + $result, 201);
    }

    #[Route('/api/transactions/{id}/validate', name: 'api_transaction_validate', methods: ['PATCH'])]
    public function validateTransaction(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $validatedAt = null;

        if (!empty($data['validatedAt'] ?? $data['date'])) {
            try {
                $validatedAt = new \DateTimeImmutable((string) ($data['validatedAt'] ?? $data['date']));
            } catch (\Exception) {
                return $this->json(['error' => 'Date de validation invalide.'], 400);
            }
        }

        $result = $this->financeService->updateTransactionValidationStatus($id, 'validated', null, $validatedAt);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Transaction validée avec succès']);
    }

    #[Route('/api/transactions/{id}/reject', name: 'api_transaction_reject', methods: ['PATCH'])]
    public function rejectTransaction(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->financeService->updateTransactionValidationStatus($id, 'rejected', $data['comment'] ?? null);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Transaction rejetée avec succès']);
    }

    #[Route('/api/transactions/{id}', name: 'api_transaction_delete', methods: ['DELETE'])]
    public function deleteTransaction(int $id): JsonResponse
    {
        $result = $this->financeService->deleteTransaction($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Transaction supprimée avec succès']);
    }



    #[Route('/api/transactions/intercompte', name: 'api_transactions_intercompte', methods: ['POST'])]
    public function intercompte(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Données requises'], 400);
        }

        $result = $this->financeService->transferInterCompte(
            $data['fromId'],
            $data['toId'],
            $data['montant'],
            $data['motif'],
            new \DateTime($data['date'])
        );

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }
}
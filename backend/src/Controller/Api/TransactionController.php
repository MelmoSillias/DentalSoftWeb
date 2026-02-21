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

        $startDate = new \DateTime($start);
        $endDate = new \DateTime($end);


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
            $data['description'],
            new \DateTime($data['date']),
            (int) $data['modeId']
        );

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json(['message' => 'Transaction enregistrée avec succès']);
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
<?php

namespace App\Controller\Admin;

use App\Entity\ModeDePaiement;
use App\Service\FinanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class FinancesController extends AbstractController
{
    public function __construct(private FinanceService $financeService)
    {
    }

    #[Route('/admin/finances', name: 'app_admin_finances')]
    public function financesIndex(): Response
    {
        $transactions = $this->financeService->getTransactions();
        $monthly = $this->financeService->computeMonthlySummary($transactions);

        return $this->render('admin/financials.html.twig', [
            'transactions'      => $transactions,
            'monthlyRevenues'   => $monthly['monthlyRevenues'],
            'monthlyExpenses'   => $monthly['monthlyExpenses'],
            'monthlyProfits'    => $monthly['monthlyProfits'],
            'soldesParCompte'   => $this->financeService->getSoldesParCompte(),
            'datasetsComptes'   => $this->financeService->getGraphDatasetsParCompteComplet(),
            'barParCompte'      => $this->financeService->getBarParCompteAnnuel(),
            'barSoldeChart'     => $this->financeService->getBarPointChartData(),
            'evolutionCapital'  => $this->financeService->getEvolutionCapitalAnnuel(),
            'active_page'       => 'finances',
        ]);
    }

    #[Route('/admin/finances/transactions', name: 'app_admin_finances_add', methods: ['POST'])]
    public function createTransaction(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $montant = $request->get('amount');
        $description = $request->get('description');
        $date = new \DateTime($request->get('date'));
        $modeId = $request->get('modeId');
        $transaction = $this->financeService->createTransaction($type, (float) $montant, $description, $date, (int) $modeId);
        if (!$transaction) {
            return $this->json(['error' => 'Mode de paiement introuvable'], 400);
        }

        return $this->json(['message' => 'Transaction enregistrée avec succès']);
    }
    

    #[Route('/admin/finances/transactions', name: 'app_admin_finances_transactions', methods: ['GET'])]
    public function getTransactionsByDateRange(Request $request): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (!$start || !$end) {
            return new JsonResponse(['error' => 'Invalid date range.'], Response::HTTP_BAD_REQUEST);
        }

        $data = $this->financeService->transactionsByDateRange(new \DateTime($start), new \DateTime($end));

        return new JsonResponse($data);
    }

    #[Route('/api/modes-paiement', name: 'api_modes_paiement_list', methods: ['GET'])]
    public function listModes(): JsonResponse
    {
        return $this->json($this->financeService->listModes());
    }

    #[Route('/api/modes-paiement', name: 'api_modes_paiement_create', methods: ['POST'])]
    public function createMode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $this->financeService->createMode($data ?? []);

        return $this->json(['message' => 'Mode de paiement créé avec succès'], 201);
    }

    #[Route('/api/modes-paiement/{id}', name: 'api_modes_paiement_delete', methods: ['DELETE'])]
    public function deleteMode(ModeDePaiement $mode): JsonResponse
    {
        $this->financeService->deleteMode($mode);
        return $this->json(['message' => 'Mode supprimé']);
    }

    #[Route('/api/modes-paiement/{id}/toggle', name: 'api_modes_paiement_toggle', methods: ['PATCH'])]
    public function toggleMode(ModeDePaiement $mode): JsonResponse
    {
        $actif = $this->financeService->toggleMode($mode);

        return $this->json([
            'message' => 'Statut mis à jour',
            'actif' => $actif,
        ]);
    }

    #[Route('/api/transactions/intercompte', name: 'api_transactions_intercompte', methods: ['POST'])]
    public function transferInterCompte(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $montant = (float) ($data['montant'] ?? 0);
        $motif = $data['motif'] ?? 'Transfert Inter-compte';
        $result = $this->financeService->transferInterCompte((int) ($data['from'] ?? 0), (int) ($data['to'] ?? 0), $montant, $motif, new \DateTime());

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], 400);
        }

        return $this->json($result);
    }

}

<?php

namespace App\Controller\Admin;

use App\Billing\Entity\ModeDePaiement;
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
        $result = $this->financeService->createTransaction($type, (float) $montant, $description, $date, (int) $modeId, $request->get('motif'));
        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
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

        $data = $this->financeService->getTransactionsByDateRange(new \DateTime($start), new \DateTime($end));

        return new JsonResponse($data);
    }

}

<?php

namespace App\Controller\Admin;

use App\Entity\ModeDePaiement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\SomeEntity;
use App\Entity\Transaction;
use App\Repository\SomeRepository;
use App\Form\SomeForm;
use App\Repository\ModeDePaiementRepository;
use App\Repository\TransactionRepository;

final class FinancesController extends AbstractController
{
    #[Route('/admin/finances', name: 'app_admin_finances')] 
    public function financesIndex(Request $request, EntityManagerInterface $entityManager, ModeDePaiementRepository $modeRepo, TransactionRepository $transactionRepo): Response
    {
        $transactions = $entityManager->getRepository(Transaction::class)
            ->findBy([], ['dateTransaction' => 'DESC']);

        $monthlyRevenues = array_fill(0, 12, 0.0);
        $monthlyExpenses = array_fill(0, 12, 0.0);

        foreach ($transactions as $transaction) {
            $month = (int) $transaction->getDateTransaction()->format('n') - 1;
            if ($transaction->getType() === 'Entrée') {
                $monthlyRevenues[$month] += $transaction->getMontant();
            } else {
                $monthlyExpenses[$month] += $transaction->getMontant();
            }
        }

        $monthlyProfits = [];
        for ($i = 0; $i < 12; $i++) {
            $monthlyProfits[$i] = $monthlyRevenues[$i] - $monthlyExpenses[$i];
        }

        return $this->render('admin/financials.html.twig', [
            'transactions' => $transactions,
            'monthlyRevenues' => $monthlyRevenues,
            'monthlyExpenses' => $monthlyExpenses,
            'monthlyProfits' => $monthlyProfits,
            'soldesParCompte' => $this->getSoldesParCompte($modeRepo), 
            'datasetsComptes' => $this->getGraphDatasetsParCompteComplet($modeRepo),
            'barParCompte' => $this->getBarParCompteAnnuel($modeRepo),
            'barSoldeChart' => $this->getBarPointChartData($modeRepo),
'evolutionCapital' => $this->getEvolutionCapitalAnnuel($transactionRepo),

            'active_page' => 'finances'
        ]);
    }

    private function getBarParCompteAnnuel(ModeDePaiementRepository $modeRepo): array
{
    $comptes = $modeRepo->findBy(['actif' => true]);
    $datasets = [
        'labels' => [],
        'entrees' => [],
        'depenses' => [],
        'soldes' => []
    ];

    foreach ($comptes as $mode) {
        $entree = 0;
        $sortie = 0;

        foreach ($mode->getTransactions() as $t) {
            if ($t->getDateTransaction()->format('Y') !== date('Y')) continue;

            if ($t->getType() === 'Entrée') {
                $entree += $t->getMontant();
            } else {
                $sortie += $t->getMontant();
            }
        }

        $datasets['labels'][] = $mode->getLibelle();
        $datasets['entrees'][] = $entree;
        $datasets['depenses'][] = $sortie;
        $datasets['soldes'][] = $entree - $sortie;
    }

    return $datasets;
}

private function getBarPointChartData(ModeDePaiementRepository $modeRepo): array
{
    $comptes = $modeRepo->findBy(['actif' => true]);
    $labels = [];
    $entrees = [];
    $depenses = [];
    $soldes = [];
    $colors = [];

    $colorPalette = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8'];
    $i = 0;

    foreach ($comptes as $mode) {
        $totalIn = 0;
        $totalOut = 0;

        foreach ($mode->getTransactions() as $t) {
            if ($t->getDateTransaction()->format('Y') !== date('Y')) continue;

            if ($t->getType() === 'Entrée') $totalIn += $t->getMontant();
            else $totalOut += $t->getMontant();
        }

        $labels[] = $mode->getLibelle();
        $entrees[] = $totalIn;
        $depenses[] = $totalOut;
        $soldes[] = $totalIn - $totalOut;
        $colors[] = $colorPalette[$i++ % count($colorPalette)];
    }

    return [
        'labels' => $labels,
        'entrees' => $entrees,
        'depenses' => $depenses,
        'soldes' => $soldes,
        'colors' => $colors
    ];
}


private function getEvolutionCapitalAnnuel(TransactionRepository $repo): array
{
    $evolution = array_fill(0, 12, 0);
    $cumul = 0;

    foreach ($repo->findAll() as $t) {
        if ($t->getDateTransaction()->format('Y') !== date('Y')) continue;

        $month = (int)$t->getDateTransaction()->format('n') - 1;
        $cumul += ($t->getType() === 'Entrée') ? $t->getMontant() : -$t->getMontant();
        $evolution[$month] = $cumul;
    }

    return $evolution;
}


    private function getGraphDatasetsParCompteComplet(ModeDePaiementRepository $modeRepo): array
    {
        $colorMap = [
            '#007bff', // Bleu
            '#28a745', // Vert
            '#ffc107', // Jaune
            '#dc3545', // Rouge
            '#6f42c1', // Violet
            '#17a2b8'  // Turquoise
        ];
    
        $datasets = [];
        $modes = $modeRepo->findBy(['actif' => true]);
        $colorIndex = 0;
    
        foreach ($modes as $mode) {
            $entrees = array_fill(0, 12, 0);
            $sorties = array_fill(0, 12, 0);
            $soldes = array_fill(0, 12, 0);
    
            foreach ($mode->getTransactions() as $t) {
                $mois = (int)$t->getDateTransaction()->format('n') - 1;
                if ($t->getType() === 'Entrée') {
                    $entrees[$mois] += $t->getMontant();
                } elseif ($t->getType() === 'Sortie') {
                    $sorties[$mois] += $t->getMontant();
                }
            }
    
            for ($i = 0; $i < 12; $i++) {
                $soldes[$i] = $entrees[$i] - $sorties[$i];
            }
    
            $mainColor = $colorMap[$colorIndex % count($colorMap)];
            $colorIndex++;
    
            $datasets[] = [
                'label' => "{$mode->getLibelle()} - Entrées",
                'data' => $entrees,
                'type' => 'bar',
                'backgroundColor' => $mainColor,
                'stack' => $mode->getId(),
            ];
            $datasets[] = [
                'label' => "{$mode->getLibelle()} - Dépenses",
                'data' => $sorties,
                'type' => 'bar',
                'backgroundColor' => $mainColor . '99', // plus clair
                'stack' => $mode->getId(),
            ];
            $datasets[] = [
                'label' => "{$mode->getLibelle()} - Solde",
                'data' => $soldes,
                'type' => 'line',
                'borderColor' => $mainColor,
                'backgroundColor' => $mainColor . '33',
                'tension' => 0.3,
                'fill' => false,
                'borderWidth' => 2
            ];
        }
    
        return $datasets;
    }

    

    private function getGraphDataParCompte(ModeDePaiementRepository $modeRepo): array
{
    $modes = $modeRepo->findBy(['actif' => true]);
    $mois = array_fill(0, 12, 0);

    $datasets = [];

    foreach ($modes as $mode) {
        $mensuel = array_fill(0, 12, 0);

        foreach ($mode->getTransactions() as $t) {
            if ($t->getType() === 'Entrée') {
                $moisIdx = (int)$t->getDateTransaction()->format('n') - 1;
                $mensuel[$moisIdx] += $t->getMontant();
            }
        }

        $datasets[] = [
            'label' => $mode->getLibelle(),
            'data' => $mensuel,
            'type' => 'bar'
        ];
    }

    return $datasets;
}


    private function getSoldesParCompte(ModeDePaiementRepository $modeRepo): array
{
    $soldes = [];

    foreach ($modeRepo->findBy(['actif' => true]) as $mode) {
        $solde = 0;
        foreach ($mode->getTransactions() as $transaction) {
            if ($transaction->getType() === 'Entrée') {
                $solde += $transaction->getMontant();
            } else {
                $solde -= $transaction->getMontant();
            }
        }

        $soldes[] = [
            'id' => $mode->getId(),
            'libelle' => $mode->getLibelle(),
            'type' => $mode->getType(),
            'solde' => $solde
        ];
    }

    return $soldes;
} 
    #[Route('/admin/finances/transactions', name: 'app_admin_finances_add', methods: ['POST'])]
    public function createTransaction(Request $request, EntityManagerInterface $em, ModeDePaiementRepository $modeRepo): JsonResponse
    {
        $type = $request->get('type');
        $montant = $request->get('amount');
        $description = $request->get('description');
        $date = new \DateTime($request->get('date'));
        $modeId = $request->get('modeId');
    
        $mode = $modeRepo->find($modeId);
        if (!$mode) {
            return $this->json(['error' => 'Mode de paiement introuvable'], 400);
        }
    
        $transaction = new Transaction();
        $transaction->setType($type === 'entry' ? 'Entrée' : 'Sortie');
        $transaction->setMontant($montant);
        $transaction->setDescription($description);
        $transaction->setDateTransaction($date);
        $transaction->setModeDePaiement($mode);
    
        $em->persist($transaction);
        $em->flush();
    
        return $this->json(['message' => 'Transaction enregistrée avec succès']);
    }
    

    #[Route('/admin/finances/transactions', name: 'app_admin_finances_transactions', methods: ['GET'])]
    public function getTransactionsByDateRange(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (!$start || !$end) {
            return new JsonResponse(['error' => 'Invalid date range.'], Response::HTTP_BAD_REQUEST);
        }

        $transactions = $entityManager->getRepository(Transaction::class)
            ->createQueryBuilder('t')
            ->where('t.dateTransaction BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($start))
            ->setParameter('end', new \DateTime($end))
            ->orderBy('t.dateTransaction', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function ($transaction) {
            return [
                'date' => $transaction->getDateTransaction()->format('d/m/Y'),
                'id' => $transaction->getId(),
                'description' => $transaction->getDescription(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getMontant(),
                'date' => $transaction->getDateTransaction()->format('Y-m-d'),
                'modeDePaiement' => [
                    'id' => $transaction->getModeDePaiement()->getId(),
                    'libelle' => $transaction->getModeDePaiement()->getLibelle(),
                    'type' => $transaction->getModeDePaiement()->getType(),
                ],
            ];
        }, $transactions);

        return new JsonResponse($data);
    }

        #[Route('/api/modes-paiement', name: 'api_modes_paiement_list', methods: ['GET'])]
        public function listModes(ModeDePaiementRepository $repo): JsonResponse
        {
            $modes = $repo->findAll();

            $data = array_map(function (ModeDePaiement $mode) {
                return [
                    'id' => $mode->getId(),
                    'libelle' => $mode->getLibelle(),
                    'type' => $mode->getType(),
                    'actif' => $mode->isActif(),
                    'notes' => $mode->getNotes()
                ];
            }, $modes);

            return $this->json($data);
        }

    #[Route('/api/modes-paiement', name: 'api_modes_paiement_create', methods: ['POST'])]
    public function createMode(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $mode = new ModeDePaiement();
        $mode->setLibelle($data['libelle'] ?? '');
        $mode->setType($data['type'] ?? '');
        $mode->setNotes($data['notes'] ?? null);
        $mode->setActif(true);

        $em->persist($mode);
        $em->flush();

        return $this->json(['message' => 'Mode de paiement créé avec succès'], 201);
    }

    #[Route('/api/modes-paiement/{id}', name: 'api_modes_paiement_delete', methods: ['DELETE'])]
    public function deleteMode(ModeDePaiement $mode, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($mode);
        $em->flush();

        return $this->json(['message' => 'Mode supprimé']);
    }

    #[Route('/api/modes-paiement/{id}/toggle', name: 'api_modes_paiement_toggle', methods: ['PATCH'])]
    public function toggleMode(ModeDePaiement $mode, EntityManagerInterface $em): JsonResponse
    {
        $mode->setActif(!$mode->isActif());
        $em->flush();

        return $this->json([
            'message' => 'Statut mis à jour',
            'actif' => $mode->isActif()
        ]);
    }

    #[Route('/api/transactions/intercompte', name: 'api_transactions_intercompte', methods: ['POST'])]
public function transferInterCompte(Request $request, EntityManagerInterface $em, ModeDePaiementRepository $modeRepo): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $from = $modeRepo->find($data['from']);
    $to = $modeRepo->find($data['to']);
    $montant = $data['montant'] ?? 0;
    $motif = $data['motif'] ?? 'Transfert Inter-compte';
    $date = new \DateTime();

    if (!$from || !$to || $from === $to) {
        return $this->json(['error' => 'Comptes invalides'], 400);
    }

    // Sortie du compte source
    $tOut = new Transaction();
    $tOut->setType('Sortie');
    $tOut->setMontant($montant);
    $tOut->setDateTransaction($date);
    $tOut->setDescription("[Transfert] vers {$to->getLibelle()} - $motif");
    $tOut->setModeDePaiement($from);
    $em->persist($tOut);

    // Entrée sur le compte destination
    $tIn = new Transaction();
    $tIn->setType('Entrée');
    $tIn->setMontant($montant);
    $tIn->setDateTransaction($date);
    $tIn->setDescription("[Transfert] depuis {$from->getLibelle()} - $motif");
    $tIn->setModeDePaiement($to);
    $em->persist($tIn);

    $em->flush();

    return $this->json(['message' => 'Transfert effectué avec succès']);
}

}

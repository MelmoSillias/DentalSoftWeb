<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Facture;
use App\Entity\Consultation;
use App\Entity\ActePose;
use App\Entity\Transaction;
use App\Repository\FactureRepository;
use App\Form\FactureType;
use App\Repository\ModeDePaiementRepository;

final class FactureController extends AbstractController
{
    #[Route('/admin/facture/{id}/payer', name: 'facture_payer', methods: ['POST'])]
    public function payerFacture(
        Request $request,
        Facture $facture,
        EntityManagerInterface $em,
        ModeDePaiementRepository $modeRepo
    ): JsonResponse {
        $modeId = $request->request->get('modeId');
    
        if (!$modeId) {
            return $this->json(['error' => 'Mode de paiement requis'], 400);
        }
    
        $mode = $modeRepo->find($modeId);
    
        if (!$mode || !$mode->isActif()) {
            return $this->json(['error' => 'Mode de paiement invalide'], 400);
        }
    
        // Marquer la facture comme payée
        $facture->setStatut(1); // payée
        $facture->setPayeLe(new \DateTime());
        $facture->setModeDePaiement($mode);
    
        // Créer la transaction associée
        $transaction = new Transaction();
        $transaction->setType('Entrée');
        $transaction->setMontant($facture->getMontantTotal());
        $transaction->setDateTransaction(new \DateTime());
        $transaction->setDescription('Paiement de la facture #' . $facture->getId());
        $transaction->setModeDePaiement($mode);
    
        $em->persist($transaction);
        $em->flush();
    
        return $this->json(['message' => 'Facture payée et transaction enregistrée.']);
    }
    
#[Route('/api/recettes/modes', name: 'api_recettes_modes', methods: ['GET'])]
public function recettesParMode(Request $request, FactureRepository $repo): JsonResponse
{
    $start = new \DateTime($request->query->get('start') ?? 'first day of this month');
    $end = new \DateTime($request->query->get('end') ?? 'last day of this month');

    $data = $repo->findPaidGroupedByMode($start, $end);

    return $this->json($data);
}



    #[Route('/admin/facture/{id}/preview', name: 'app_admin_facture_preview', methods: ['GET'])]
    public function previewFacture(Facture $facture): JsonResponse
    {
        if (!$facture) {
            return new JsonResponse(['error' => 'Facture introuvable'], Response::HTTP_NOT_FOUND);
        }

        $consultation = $facture->getConsultation();
        $actes = $consultation->getActes()->map(function ($acte) {
            return [
                'nom' => $acte->getDescription(),
                'description' => $acte->getDescription(),
                'cout' => $acte->getPrix(),
                'quantite' => 1,
            ];
        })->toArray();

        $data = [
            'id' => $facture->getId(),
            'dateEmission' => $facture->getDateEmission()->format('Y-m-d'),
            'total' => $facture->getMontantTotal(),
            'consultation' => [
                'id' => $consultation->getId(),
                'patient' => [
                    'nom' => $consultation->getPatient()->getNom(),
                    'prenom' => $consultation->getPatient()->getPrenom(),
                    'telephone' => $consultation->getPatient()->getTelephone(),
                ],
            ],
            'actes' => $actes,
        ];

        return new JsonResponse($data);
    }

    #[Route('/api/factures/payees', name: 'api_factures_payees')]
    public function getPaidInvoicesApi(Request $request, FactureRepository $factureRepo): JsonResponse
    {
        // 1) Période par défaut : tout le mois à pleine journée
        $start = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
        $end   = (new \DateTime('last day of this month'))->setTime(23, 59, 59);
    
        // 2) Si on a start/end dans la query, on les parse et on force les heures
        if ($request->query->has('start') && $request->query->has('end')) {
            $s = \DateTime::createFromFormat('Y-m-d', $request->query->get('start'));
            $e = \DateTime::createFromFormat('Y-m-d', $request->query->get('end'));
    
            if ($s instanceof \DateTime) {
                $start = $s->setTime(0, 0, 0);
            }
            if ($e instanceof \DateTime) {
                $end = $e->setTime(23, 59, 59);
            }
        }
    
        // 3) Récupération des données
        $paidData = $factureRepo->findPaidByPeriod($start, $end);
    
        // 4) Format de sortie pour DataTables
        $rows = array_map(function($facture) {
            return [
                'id'           => $facture->getId(),
                'patient'      => $facture->getConsultation()->getPatient()->getNom()
                                 . ' ' .
                                 $facture->getConsultation()->getPatient()->getPrenom(),
                'montantTotal' => $facture->getMontantTotal(),
                'payeLe'       => $facture->getPayeLe()->format('Y-m-d H:i:s'),
                'actions'      => $facture->getId(),
            ];
        }, $paidData['factures']);
    
        return new JsonResponse([
            'data'            => $rows,
            'recordsTotal'    => $paidData['count'],
            'recordsFiltered' => $paidData['count'],
        ]);
    }
    

    // ==== Consultations ====
}

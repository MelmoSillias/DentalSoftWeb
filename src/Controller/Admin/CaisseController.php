<?php

namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Service\CashdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CaisseController extends AbstractController
{
    public function __construct(private CashdeskService $cashdeskService)
    {
    }

    #[Route('/admin/caisse', name: 'app_admin_caisse')]
    public function caisse(): Response
    {
        return $this->render('admin/caisse.html.twig', [
            'active_page' => 'caisse'
        ]);
    }

    #[Route('/api/devis/all', name: 'api_devis_all', methods: ['GET'])]
    public function getDevisAll(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end   = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        return new JsonResponse($this->cashdeskService->listDevisByPeriod($start, $end));
    }

    #[Route('/api/devis/impayes', name: 'api_devis_impayes', methods: ['GET'])]
    public function getDevisImpayes(): JsonResponse
    {
        return new JsonResponse($this->cashdeskService->listDevisImpayes());
    }

    #[Route('/api/paiements-devis', name: 'api_paiements_devis', methods: ['GET'])]
    public function getPaiementsDevis(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end   = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        return new JsonResponse(['data' => $this->cashdeskService->listPaiementsDevis($start, $end)]);
    }


    #[Route('/api/devis/{id}/preview', name: 'api_devis_preview', methods: ['GET'])]
    public function previewDevis(Devis $devis): JsonResponse
    {
        $data = $this->cashdeskService->previewDevis($devis->getId());

        if ($data === null) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/devis/{id}/payer', name: 'api_devis_payer', methods: ['POST'])]
    public function payerDevis(int $id, Request $request): JsonResponse
    {
        $result = $this->cashdeskService->payerDevis(
            $id,
            (int) $request->request->get('modeId'),
            (float) $request->request->get('montant', 0)
        );

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/paiement-devis/impression', name: 'api_paiements_devis_print', methods: ['GET'])]
    public function printListePaiements(Request $request): Response
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        $paiements = $this->cashdeskService->paiementsForPeriod($start, $end);

        return $this->render('devis/print_paiements_liste.html.twig', [
            'paiements' => $paiements,
            'start' => $start,
            'end' => $end
        ]);
    }

    #[Route('/api/paiement-devis/{id}/print', name: 'api_paiement_devis_print', methods: ['GET'])]
    public function printPaiement(int $id): Response
    {
        $paiement = $this->cashdeskService->paiementById($id);
        if (!$paiement) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        return $this->render('devis/print_paiement.html.twig', [
            'paiement' => $paiement
        ]);
    }

    #[Route('/api/paiement-ticket/{id}/print', name: 'api_paiement_ticket_print', methods: ['GET'])]
    public function printTicket(int $id): Response
    {
        $paiement = $this->cashdeskService->paiementById($id);
        if (!$paiement) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        return $this->render('devis/print_Ticket.html.twig', [
            'paiement' => $paiement
        ]);
    }
}

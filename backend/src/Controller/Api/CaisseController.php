<?php

namespace App\Controller\Api;

use App\Entity\Devis;
use App\Entity\PaiementDevis;
use App\Service\CashdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CaisseController extends AbstractController
{
    public function __construct(private CashdeskService $cashdeskService)
    {
    }

    #[Route('/api/devis', name: 'api_devis_list', methods: ['GET'])]
    public function getDevisAll(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return new JsonResponse($this->cashdeskService->listDevisByPeriod($start, $end));
    }

    #[Route('/api/devis/unpaid', name: 'api_devis_unpaid', methods: ['GET'])]
    public function getDevisImpayes(): JsonResponse
    {
        return new JsonResponse($this->cashdeskService->listDevisImpayes());
    }

    #[Route('/api/devis/payments', name: 'api_devis_payments', methods: ['GET'])]
    public function getPaiementsDevis(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return new JsonResponse(['data' => $this->cashdeskService->listPaiementsDevis($start, $end)]);
    }

    #[Route('/api/devis/{id}', name: 'api_devis_preview', methods: ['GET'])]
    public function previewDevis(Devis $devis): JsonResponse
    {
        $data = $this->cashdeskService->previewDevis($devis->getId());

        if ($data === null) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/devis/{id}/pay', name: 'api_devis_pay', methods: ['POST'])]
    public function payerDevis(int $id, Request $request): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $result = $this->cashdeskService->payerDevis(
            $id,
            (int) ($payload['modeId'] ?? 0),
            (float) ($payload['montant'] ?? 0),
            $payload['date'] ?? null,
            $payload['time'] ?? null
        );

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/devis/{id}/print', name: 'api_devis_print', methods: ['GET'])]
    public function printDevis(int $id): Response
    {
        $data = $this->cashdeskService->previewDevis($id);
        if ($data === null) {
            throw $this->createNotFoundException('Devis introuvable.');
        }

        return $this->render('devis/print_document.html.twig', [
            'doc' => $data,
            'title' => 'Devis',
        ]);
    }

    #[Route('/api/invoices/{id}/print', name: 'api_invoice_print', methods: ['GET'])]
    public function printFacture(int $id): Response
    {
        $data = $this->cashdeskService->previewFacture($id);
        if ($data === null) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('devis/print_document.html.twig', [
            'doc' => $data,
            'title' => 'Facture',
        ]);
    }

    #[Route('/api/payments/print', name: 'api_payments_print', methods: ['GET'])]
    public function printListePaiements(Request $request): Response
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        $paiements = $this->cashdeskService->paiementsForPeriod($start, $end);

        return $this->render('devis/print_paiements_liste.html.twig', [
            'paiements' => $paiements,
            'start' => $start,
            'end' => $end
        ]);
    }

    #[Route('/api/payments/{id}/print', name: 'api_payment_print', methods: ['GET'])]
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

    #[Route('/api/receipts/{id}/print', name: 'api_receipt_print', methods: ['GET'])]
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

    #[Route('/api/prints/devis/{id}', name: 'api_print_devis_data', methods: ['GET'])]
    public function getDevisPrintData(int $id): JsonResponse
    {
        $data = $this->cashdeskService->previewDevis($id);
        if ($data === null) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $data,
            'title' => 'Devis',
        ]);
    }

    #[Route('/api/prints/invoices/{id}', name: 'api_print_invoice_data', methods: ['GET'])]
    public function getFacturePrintData(int $id): JsonResponse
    {
        $data = $this->cashdeskService->previewFacture($id);
        if ($data === null) {
            return new JsonResponse(['error' => 'Facture introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $data,
            'title' => 'Facture',
        ]);
    }

    #[Route('/api/prints/payments', name: 'api_print_payments_data', methods: ['GET'])]
    public function getPaymentsPrintData(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        $paiements = $this->cashdeskService->paiementsForPeriod($start, $end);
        $items = array_map(fn (PaiementDevis $p) => $this->mapPaiementListItem($p), $paiements);
        $total = array_reduce($items, fn ($sum, $p) => $sum + (float) ($p['montant'] ?? 0), 0);

        return new JsonResponse([
            'paiements' => $items,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'total' => $total,
        ]);
    }

    #[Route('/api/prints/receipts/{id}', name: 'api_print_receipt_data', methods: ['GET'])]
    public function getReceiptPrintData(int $id): JsonResponse
    {
        $paiement = $this->cashdeskService->paiementById($id);
        if (!$paiement) {
            return new JsonResponse(['error' => 'Paiement introuvable'], 404);
        }

        return new JsonResponse([
            'paiement' => $this->mapPaiementReceipt($paiement),
        ]);
    }

    #[Route('/api/prints/tickets/{id}', name: 'api_print_ticket_data', methods: ['GET'])]
    public function getTicketPrintData(int $id): JsonResponse
    {
        $paiement = $this->cashdeskService->paiementById($id);
        if (!$paiement) {
            return new JsonResponse(['error' => 'Paiement introuvable'], 404);
        }

        return new JsonResponse([
            'paiement' => $this->mapPaiementTicket($paiement),
        ]);
    }

    private function mapPaiementReceipt(PaiementDevis $paiement): array
    {
        $devis = $paiement->getDevis();
        $fiche = $devis?->getFicheMedicale();
        $patient = $fiche?->getPatient();

        return [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'devis' => $devis ? [
                'id' => $devis->getId(),
                'fiche' => $fiche ? [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ] : null,
            ] : null,
        ];
    }

    private function mapPaiementTicket(PaiementDevis $paiement): array
    {
        $consultation = $paiement->getConsultation();
        $patient = $consultation?->getPatient();

        return [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'consultation' => $consultation ? [
                'patient' => $patient ? [
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
            ] : null,
        ];
    }

    private function mapPaiementListItem(PaiementDevis $paiement): array
    {
        $devis = $paiement->getDevis();
        $fiche = $devis?->getFicheMedicale();
        $patient = $fiche?->getPatient();

        return [
            'devis' => $devis ? [
                'id' => $devis->getId(),
                'fiche' => $fiche ? [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ] : null,
            ] : null,
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
        ];
    }
}
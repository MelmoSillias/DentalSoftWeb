<?php

namespace App\Billing\Controller\Api;

use App\Billing\Entity\Paiement;
use App\Billing\Repository\PaiementRepository;
use App\Billing\Service\InsuranceClaimService;
use App\Patient\Entity\Patient;
use App\Billing\Service\CashdeskService;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ConsultationRepository;
use App\Communication\Service\SmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CaisseController extends AbstractController
{
    public function __construct(
        private CashdeskService $cashdeskService,
        private InsuranceClaimService $insuranceClaimService,
        private SmsService $smsService,
        private PaiementRepository $paiementRepository,
        private ConsultationRepository $consultationRepository,
    )
    {
    }

    #[Route('/api/factures', name: 'api_factures_list', methods: ['GET'])]
    public function getFacturesAll(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return new JsonResponse($this->cashdeskService->listFacturesByPeriod($start, $end));
    }

    #[Route('/api/factures/classiques', name: 'api_factures_classiques_list', methods: ['GET'])]
    public function getFacturesClassiques(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return new JsonResponse($this->cashdeskService->listFacturesByPeriod($start, $end));
    }

    #[Route('/api/factures/assurances', name: 'api_factures_assurances_list', methods: ['GET'])]
    public function getFacturesAssurances(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $patient = $request->query->get('patient');
        $assuranceCode = $request->query->get('assuranceCode', $request->query->get('assurance'));

        $start = null;
        if ($request->query->has('start') && $request->query->get('start') !== '') {
            try {
                $start = new \DateTime((string) $request->query->get('start'));
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Date de debut invalide'], 400);
            }
        }

        $end = null;
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            try {
                $end = new \DateTime((string) $request->query->get('end'));
                $end->setTime(23, 59, 59);
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Date de fin invalide'], 400);
            }
        }

        $data = $this->insuranceClaimService->listClaims(
            is_string($status) ? $status : null,
            $start,
            $end,
            is_string($patient) ? $patient : null,
            is_string($assuranceCode) ? $assuranceCode : null,
        );

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/api/factures/unpaid', name: 'api_factures_unpaid', methods: ['GET'])]
    public function getFacturesImpayees(): JsonResponse
    {
        return new JsonResponse($this->cashdeskService->listFacturesImpayees());
    }

    #[Route('/api/factures/payments', name: 'api_factures_payments', methods: ['GET'])]
    public function getPaiementsFactures(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return new JsonResponse(['data' => $this->cashdeskService->listPaiementsFactures($start, $end)]);
    }

    #[Route('/api/factures/{id}', name: 'api_factures_preview', methods: ['GET'])] 
    public function previewFacture(int $id): JsonResponse
    {
        $data = $this->cashdeskService->previewFactureDetail($id);

        if ($data === null) {
            return new JsonResponse(['error' => 'Facture introuvable'], 404);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/factures/{id}/pay', name: 'api_factures_pay', methods: ['POST'])] 
    public function payerFacture(int $id, Request $request): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $result = $this->cashdeskService->payerFacture($id, $payload);

        if (!isset($result['error']) && isset($result['paiement_id'])) {
            $paiement = $this->paiementRepository->find((int) $result['paiement_id']);
            $patient = $this->resolvePatientFromPaiement($paiement);

            if ($patient instanceof Patient) {
                $this->smsService->queueTemplateForPatient($patient, 'receipt', [
                    'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
                    'amount' => (string) ((int) round((float) ($payload['montant'] ?? 0))),
                    'date' => (string) ($payload['date'] ?? (new \DateTime())->format('Y-m-d')),
                    'cabinet_name' => 'ORODENT',
                ], 'payment');
            }
        }

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/factures/{id}/payments/reset', name: 'api_factures_payments_reset', methods: ['DELETE'])] 
    public function resetFacturePayments(int $id): JsonResponse
    {
        $result = $this->cashdeskService->resetFacturePayments($id);

        return new JsonResponse($result, isset($result['error']) ? 404 : 200);
    }

    #[Route('/api/factures/{id}/print', name: 'api_factures_print', methods: ['GET'])] 
    public function printFactureFromLegacyRoute(int $id): Response
    {
        $data = $this->cashdeskService->previewFactureDetail($id);
        if ($data === null) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('devis/print_document.html.twig', [
            'doc' => $data,
            'title' => 'Facture',
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

    #[Route('/api/prints/factures/{id}', name: 'api_print_factures_data', methods: ['GET'])] 
    public function getFacturePrintDataLegacy(int $id): JsonResponse
    {
        $data = $this->cashdeskService->previewFactureDetail($id);
        if ($data === null) {
            return new JsonResponse(['error' => 'Facture introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $data,
            'title' => 'Facture',
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
        $items = array_map(fn (Paiement $p) => $this->mapPaiementListItem($p), $paiements);
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

        $consultation = $this->consultationRepository->find($id);
        if (!$consultation) {
            return new JsonResponse(['error' => 'Consultation introuvable'], 404);
        }

        return new JsonResponse([
            'paiement' => $this->mapPaiementTicket($consultation),
        ]);
    }

    private function mapPaiementReceipt(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();

        return [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'devis' => $factureId ? [
                'id' => $factureId,
                'reste' => $paiement->getFacture()->computeMontantsFromConsultation()['reste'] ?? 0.0,
                'fiche' => [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ],
            ] : null,
        ];
    }

    private function mapPaiementTicket(Consultation $consultation): array
    {
        $patient = $consultation?->getPatient();

        return [
            'id' => $consultation->getId(),
            'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
            'montant' => $consultation->getPaiement()?->getMontant(),
            'mode' => [
                'libelle' => $consultation->getPaiement()?->getMode()?->getLibelle(),
            ],
            'consultation' => $consultation ? [
                'patient' => $patient ? [
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
            ] : null,
        ];
    }

    private function mapPaiementListItem(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();

        return [
            'devis' => $factureId ? [
                'id' => $factureId,
                'fiche' => [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ],
            ] : null,
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
        ];
    }

    private function resolvePatientFromPaiement(?Paiement $paiement): ?Patient
    {
        if (!$paiement instanceof Paiement) {
            return null;
        }

        $facture = $paiement->getFacture();
        $fromFicheMedicale = $facture?->getConsultation()?->getFicheMedicale()?->getPatient();
        if ($fromFicheMedicale instanceof Patient) {
            return $fromFicheMedicale;
        }

        $fiche = $facture?->getConsultation()?->getFiche();
        if ($fiche && method_exists($fiche, 'getPatient')) {
            $patient = $fiche->getPatient();
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return $paiement->getConsultation()?->getPatient()
            ?? $paiement->getFacture()?->getConsultation()?->getPatient();
    }
}

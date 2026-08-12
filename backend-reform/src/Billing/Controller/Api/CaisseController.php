<?php

namespace App\Billing\Controller\Api;

use App\Billing\Application\Command\PayFacture\PayFactureCommand;
use App\Billing\Application\Command\ResetFactureAssurancePayments\ResetFactureAssurancePaymentsCommand;
use App\Billing\Application\Command\ResetFacturePayments\ResetFacturePaymentsCommand;
use App\Billing\Application\Query\GetDevis\GetDevisQuery;
use App\Billing\Application\Query\GetFactureAssurancePrint\GetFactureAssurancePrintQuery;
use App\Billing\Application\Query\GetFacturePreview\GetFacturePreviewQuery;
use App\Billing\Application\Query\ListFactures\ListFacturesQuery;
use App\Billing\Application\Query\ListFacturesAssurance\ListFacturesAssuranceQuery;
use App\Billing\Application\Query\ListPaiementsFactures\ListPaiementsFacturesQuery;
use App\Billing\Domain\Exception\DevisNotFoundException;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Paiement;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\PaiementRepository;
use App\Billing\Service\CashdeskEntryPointService;
use App\Communication\Service\SmsService;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Settings\Service\GlobalSettingsService;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CaisseController extends AbstractController
{
    public function __construct(
        private CashdeskEntryPointService $entryPoint,
        private SmsService $smsService,
        private GlobalSettingsService $globalSettingsService,
        private PaiementRepository $paiementRepository,
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    )
    {
    }

    #[Route('/api/factures', name: 'api_factures_list', methods: ['GET'])]
    public function getFacturesAll(Request $request): JsonResponse
    {
        [$start, $end] = $this->resolvePeriod($request);

        return new JsonResponse($this->queryBus->ask(new ListFacturesQuery(
            ListFacturesQuery::SCOPE_ALL,
            $start,
            $end,
        )));
    }

    #[Route('/api/factures/classiques', name: 'api_factures_classiques_list', methods: ['GET'])]
    public function getFacturesClassiques(Request $request): JsonResponse
    {
        [$start, $end] = $this->resolvePeriod($request);

        return new JsonResponse($this->queryBus->ask(new ListFacturesQuery(
            ListFacturesQuery::SCOPE_CLASSIQUES,
            $start,
            $end,
        )));
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

        $data = $this->queryBus->ask(new ListFacturesAssuranceQuery(
            $start,
            $end,
            is_string($status) ? $status : null,
            is_string($patient) ? $patient : null,
            is_string($assuranceCode) ? $assuranceCode : null,
        ));

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/api/factures/unpaid', name: 'api_factures_unpaid', methods: ['GET'])]
    public function getFacturesImpayees(Request $request): JsonResponse
    {
        $start = null;
        $end = null;

        if ($request->query->has('start') && $request->query->get('start') !== '') {
            try {
                $start = new \DateTime((string) $request->query->get('start'));
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Date de debut invalide'], 400);
            }
        }

        if ($request->query->has('end') && $request->query->get('end') !== '') {
            try {
                $end = new \DateTime((string) $request->query->get('end'));
                $end->setTime(23, 59, 59);
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Date de fin invalide'], 400);
            }
        }

        if ($start !== null && $end === null) {
            $end = (clone $start)->setTime(23, 59, 59);
        }

        return new JsonResponse($this->queryBus->ask(new ListFacturesQuery(
            ListFacturesQuery::SCOPE_UNPAID,
            $start,
            $end,
        )));
    }

    #[Route('/api/factures/payments', name: 'api_factures_payments', methods: ['GET'])]
    public function getPaiementsFactures(Request $request): JsonResponse
    {
        [$start, $end] = $this->resolvePeriod($request);

        return new JsonResponse([
            'data' => $this->queryBus->ask(new ListPaiementsFacturesQuery($start, $end)),
        ]);
    }

    #[Route('/api/factures/{id}', name: 'api_factures_preview', methods: ['GET'])] 
    public function previewFacture(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetFacturePreviewQuery(
            $id,
            GetFacturePreviewQuery::VARIANT_DETAIL,
        ));

        if ($data === null) {
            return new JsonResponse(['error' => 'Facture introuvable'], 404);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/factures/{id}/pay', name: 'api_factures_pay', methods: ['POST'])] 
    public function payerFacture(int $id, Request $request): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $result = $this->commandBus->dispatch(new PayFactureCommand($id, $payload));

        if (!isset($result['error']) && isset($result['paiement_id'])) {
            $paiement = $this->paiementRepository->find((int) $result['paiement_id']);
            $patient = $this->entryPoint->resolvePatientFromPaiement($paiement);

            if ($patient instanceof Patient) {
                $this->smsService->queueTemplateForPatient($patient, 'receipt', [
                    'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
                    'amount' => (string) ((int) round((float) ($payload['montant'] ?? 0))),
                    'date' => (string) ($payload['date'] ?? (new \DateTime())->format('Y-m-d')),
                    'cabinet_name' => $this->globalSettingsService->resolveCabinetName(),
                ], 'payment');
            }
        }

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/factures/{id}/payments/reset', name: 'api_factures_payments_reset', methods: ['DELETE'])] 
    public function resetFacturePayments(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new ResetFacturePaymentsCommand($id));

        return new JsonResponse($result, isset($result['error']) ? 404 : 200);
    }

    #[Route('/api/factures/assurance/{id}/payments/reset', name: 'api_factures_assurance_payments_reset', methods: ['DELETE'])]
    public function resetFactureAssurancePayments(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new ResetFactureAssurancePaymentsCommand($id));

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    #[Route('/api/factures/{id}/print', name: 'api_factures_print', methods: ['GET'])] 
    public function printFactureFromLegacyRoute(int $id): Response
    {
        $data = $this->queryBus->ask(new GetFacturePreviewQuery(
            $id,
            GetFacturePreviewQuery::VARIANT_DETAIL,
        ));
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
        $data = $this->queryBus->ask(new GetFacturePreviewQuery(
            $id,
            GetFacturePreviewQuery::VARIANT_PRINT,
        ));
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

        $paiements = $this->entryPoint->paiementsForPeriod($start, $end);

        return $this->render('devis/print_paiements_liste.html.twig', [
            'paiements' => $paiements,
            'start' => $start,
            'end' => $end
        ]);
    }

    #[Route('/api/payments/{id}/print', name: 'api_payment_print', methods: ['GET'])]
    public function printPaiement(int $id): Response
    {
        $paiement = $this->entryPoint->paiementById($id);
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
        $paiement = $this->entryPoint->paiementById($id);
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
        try {
            $data = $this->queryBus->ask(new GetDevisQuery($id));
        } catch (DevisNotFoundException) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $data,
            'title' => 'Devis',
        ]);
    }

    #[Route('/api/prints/factures/{id}', name: 'api_print_factures_data', methods: ['GET'])] 
    public function getFacturePrintDataLegacy(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetFacturePreviewQuery(
            $id,
            GetFacturePreviewQuery::VARIANT_DETAIL,
        ));
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
        $data = $this->queryBus->ask(new GetFacturePreviewQuery(
            $id,
            GetFacturePreviewQuery::VARIANT_PRINT,
        ));
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

        $paiements = $this->entryPoint->paiementsForPeriod($start, $end);
        $items = array_map(fn (Paiement $p) => $this->entryPoint->mapPaiementListItem($p), $paiements);
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
        $paiement = $this->entryPoint->paiementById($id);
        if (!$paiement) {
            return new JsonResponse(['error' => 'Paiement introuvable'], 404);
        }

        return new JsonResponse([
            'paiement' => $this->entryPoint->mapPaiementReceipt($paiement),
        ]);
    }

    #[Route('/api/prints/tickets/{id}', name: 'api_print_ticket_data', methods: ['GET'])]
    public function getTicketPrintData(int $id): JsonResponse
    {
        $paiement = $this->entryPoint->paiementById($id);
        if (!$paiement) {
            return new JsonResponse(['error' => 'Paiement introuvable'], 404);
        }

        return new JsonResponse([
            'paiement' => $this->entryPoint->mapPaiementTicket($paiement),
        ]);
    }

    #[Route('/api/prints/assurances/claims/{id}', name: 'api_print_assurance_claim_data', methods: ['GET'])]
    public function getFactureAssurancePrintData(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetFactureAssurancePrintQuery($id));
        if ($data === null) {
            return new JsonResponse(['error' => 'Facture assurance introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $data,
            'title' => 'Facture assurance',
        ]);
    }

    /**
     * @return array{0: \DateTime, 1: \DateTime}
     */
    private function resolvePeriod(Request $request): array
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        if ($request->query->has('end') && $request->query->get('end') !== '') {
            $end = new \DateTime($request->query->get('end'));
        } else {
            $end = (clone $start);
        }
        $end->setTime(23, 59, 59);

        return [$start, $end];
    }
}

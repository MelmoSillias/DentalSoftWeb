<?php

namespace App\Billing\Service\Workflow;

use App\Billing\Entity\FactureAssurance;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Service\InsuranceClaimService;
use DateTimeInterface;

class InsuredInvoiceWorkflowService
{
    public function __construct(
        private InsuranceClaimService $insuranceClaimService,
        private FactureAssuranceRepository $factureAssuranceRepo,
    ) {
    }

    public function listFacturesAssurance(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?string $status = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
    ): array {
        return $this->insuranceClaimService->listClaims(
            $status,
            $start,
            $end,
            $patientQuery,
            $assuranceCode,
        );
    }

    /**
     * Returns insurance invoices normalized to the same shape as classic factures,
     * so the cashdesk overview can display both in a single list.
     */
    public function listFacturesAssuranceForCashdesk(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $claims = $this->insuranceClaimService->listClaims(null, $start, $end);

        return array_map(static function (array $claim): array {
            $montantPatient = (float) ($claim['montantPatient'] ?? 0.0);
            $patientPaid = (float) ($claim['patientPaidAmount'] ?? 0.0);
            $restePatient = (float) ($claim['restePatient'] ?? max(0.0, $montantPatient - $patientPaid));
            $montantTotal = (float) ($claim['montantTotal'] ?? 0.0);
            $insuranceStatus = $claim['insuranceStatus'] ?? 'pending';
            $isRegle = $insuranceStatus === 'validated_empty'
                || ($montantTotal > 0.0 && $restePatient <= 0.0);

            $patientName = $claim['patient'] ?? '';
            $telephone = $claim['telephone'] ?? '';

            return [
                'id' => $claim['id'],
                'factureAssuranceId' => $claim['factureId'] ?? $claim['id'],
                'date' => isset($claim['dateFacture']) ? (new \DateTime($claim['dateFacture']))->format('Y-m-d') : null,
                'consultation' => $claim['consultationId'] ?? null,
                'montant' => $montantPatient,
                'montantTotal' => $montantTotal,
                'montantPatient' => $montantPatient,
                'montantAssureur' => (float) ($claim['montantAssurance'] ?? 0.0),
                'reste' => $restePatient,
                'statut' => $isRegle ? 1 : 0,
                'isRegle' => $isRegle,
                'hasPayments' => $patientPaid > 0,
                'patient' => is_string($patientName)
                    ? $patientName
                    : ['nom' => '', 'prenom' => ''],
                'telephone' => $telephone,
                'contenus' => [],
                'paiements' => [],
                'type' => 'FactureAssurance',
                'insurance' => [
                    'hasInsurance' => true,
                    'assuranceId' => $claim['assurance']['id'] ?? null,
                    'assuranceNom' => $claim['assurance']['nom'] ?? null,
                    'assuranceCode' => $claim['assurance']['code'] ?? null,
                    'logoPath' => $claim['assurance']['logoPath'] ?? null,
                    'tauxCouverture' => $claim['tauxCouverture'] ?? 0,
                    'insuranceRate' => $claim['tauxCouverture'] ?? 0,
                    'montantTotal' => $montantTotal,
                    'montantAssurance' => (float) ($claim['montantAssurance'] ?? 0.0),
                    'insuranceAmount' => (float) ($claim['montantAssurance'] ?? 0.0),
                    'montantPatient' => $montantPatient,
                    'patientPaidAmount' => $patientPaid,
                    'patientRemainingAmount' => $restePatient,
                    'restePatient' => $restePatient,
                    'insuranceStatus' => $claim['insuranceStatus'] ?? 'pending',
                    'factureAssuranceId' => $claim['factureId'] ?? $claim['id'],
                    'lotId' => $claim['lotId'] ?? null,
                    'lotStatut' => $claim['lotStatut'] ?? null,
                    'consultationAmount' => (float) ($claim['consultationAmount'] ?? 0.0),
                ],
            ];
        }, $claims);
    }

    public function getClaimDetail(int $factureId): array
    {
        return $this->insuranceClaimService->getClaimDetail($factureId);
    }

    public function payPatientShare(int $factureId, int $modeId, ?float $amount = null, ?DateTimeInterface $date = null): array
    {
        return $this->insuranceClaimService->payPatientShare($factureId, $modeId, $amount, $date);
    }

    public function mapFactureAssurancePrint(int $id): ?array
    {
        $facture = $this->factureAssuranceRepo->find($id);
        if (!$facture instanceof FactureAssurance) {
            return null;
        }

        $totals = $facture->computeTotals();
        $patient = $facture->getConsultation()?->getPatient() ?? $facture->getPatient();
        $lot = $facture->getLotFactureAssurance();
        $montantPatient = (float) ($totals['montantPatient'] ?? 0.0);
        $patientPaid = $facture->computePatientPaidAmount();
        $lines = [];

        foreach ($facture->buildDisplayLignes() as $line) {
            $lines[] = [
                'designation' => $line['designation'] ?? 'Soin',
                'description' => $line['description'] ?? '',
                'quantite' => $line['quantite'] ?? 1,
                'prix' => $line['prix'] ?? 0,
                'total' => $line['total'] ?? 0,
                'virtual' => !empty($line['virtual']),
            ];
        }

        return [
            'id' => $facture->getId(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i'),
            'patient' => [
                'nom' => $patient?->getNom(),
                'prenom' => $patient?->getPrenom(),
                'telephone' => $patient?->getTelephone(),
            ],
            'assurance' => [
                'nom' => $facture->getAssurance()?->getNom(),
                'code' => $facture->getAssurance()?->getCode(),
                'tauxCouverture' => $facture->getCoverageRate(),
                'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
                'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
                'montantPatient' => $montantPatient,
                'partPatientPayee' => $patientPaid,
                'restePatient' => max(0.0, $montantPatient - $patientPaid),
                'insuranceStatus' => $facture->getInsuranceStatus(),
                'factureAssuranceId' => $facture->getId(),
            ],
            'lignes' => $lines,
            'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
            'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
            'montantPatient' => $montantPatient,
            'tauxCouverture' => $facture->getCoverageRate(),
            'insuranceStatus' => $facture->getInsuranceStatus(),
            'isRecouvre' => $facture->isRecouvre(),
            'lot' => $lot ? [
                'id' => $lot->getId(),
                'description' => $lot->getDescription(),
                'statut' => $lot->getStatut(),
            ] : null,
        ];
    }
}

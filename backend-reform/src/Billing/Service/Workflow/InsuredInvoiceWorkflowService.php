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

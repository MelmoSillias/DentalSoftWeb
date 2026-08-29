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

        return array_map(fn (array $claim): array => $this->mapClaimToCashdeskRow($claim), $claims);
    }

    /**
     * @param array<string, mixed> $claim
     * @return array<string, mixed>
     */
    public function mapClaimToCashdeskRow(array $claim): array
    {
        $montantPatient = (float) ($claim['montantPatient'] ?? 0.0);
        $patientPaid = (float) ($claim['patientPaidAmount'] ?? 0.0);
        $restePatient = (float) ($claim['restePatient'] ?? max(0.0, $montantPatient - $patientPaid));
        $montantTotal = (float) ($claim['montantTotal'] ?? 0.0);
        $insuranceStatus = $claim['insuranceStatus'] ?? 'pending';
        $isRegle = $insuranceStatus === 'validated_empty'
            || ($montantTotal > 0.0 && $restePatient <= 0.0);

        $patientName = $claim['patient'] ?? '';
        $telephone = $claim['telephone'] ?? '';
        $patientId = isset($claim['patientId']) ? (int) $claim['patientId'] : null;

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
                ? ['id' => $patientId, 'nom' => $patientName, 'prenom' => '']
                : ['id' => $patientId, 'nom' => '', 'prenom' => ''],
            'patientId' => $patientId,
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
    }

    public function getClaimDetail(int $factureId): array
    {
        return $this->insuranceClaimService->getClaimDetail($factureId);
    }

    public function mapFactureAssuranceToCashdeskRow(int $factureAssuranceId): ?array
    {
        $detail = $this->insuranceClaimService->getClaimDetail($factureAssuranceId);
        if (isset($detail['error']) || !isset($detail['data']) || !is_array($detail['data'])) {
            return null;
        }

        return $this->mapClaimToCashdeskRow($detail['data']);
    }

    public function payPatientShare(int $factureId, int $modeId, ?float $amount = null, ?DateTimeInterface $date = null): array
    {
        return $this->insuranceClaimService->payPatientShare($factureId, $modeId, $amount, $date);
    }

    public function resetPayments(int $factureId): array
    {
        return $this->insuranceClaimService->resetPayments($factureId);
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
        $montantTotal = (float) ($totals['montantTotal'] ?? 0.0);
        $montantAssurance = (float) ($totals['montantAssureur'] ?? 0.0);
        $montantPatient = (float) ($totals['montantPatient'] ?? 0.0);
        $patientPaid = $facture->computePatientPaidAmount();
        $restePatient = max(0.0, $montantPatient - $patientPaid);
        $snapshot = $facture->getAssuranceSnapshot() ?: [];
        $assurance = $facture->getAssurance();
        $lines = [];
        $contenus = [];

        $hasCabinetServices = false;
        foreach ($facture->buildDisplayLignes() as $line) {
            $quantite = (int) ($line['quantite'] ?? 1);
            $prix = (float) ($line['prix'] ?? 0);
            $total = (float) ($line['total'] ?? 0);
            $designation = (string) ($line['designation'] ?? 'Soin');

            $lines[] = [
                'designation' => $designation,
                'description' => $line['description'] ?? '',
                'quantite' => $quantite,
                'prix' => $prix,
                'total' => $total,
                'virtual' => !empty($line['virtual']),
                'attribution' => $line['attribution'] ?? 'medecin',
            ];
            if (($line['attribution'] ?? 'medecin') === 'cabinet') {
                $hasCabinetServices = true;
            }
            $contenus[] = [
                'designation' => $designation,
                'qte' => $quantite,
                'montant' => $prix,
                'total' => $total,
                'attribution' => $line['attribution'] ?? 'medecin',
            ];
        }

        $dateFacture = $facture->getDateFacture()?->format('Y-m-d H:i');
        $dateShort = $facture->getDateFacture()?->format('Y-m-d');

        return [
            'id' => $facture->getId(),
            'date' => $dateShort,
            'dateFacture' => $dateFacture,
            'patient' => [
                'nom' => $patient?->getNom(),
                'prenom' => $patient?->getPrenom(),
                'telephone' => $patient?->getTelephone(),
            ],
            'assurance' => [
                'nom' => $assurance?->getNom() ?? ($snapshot['nom'] ?? null),
                'code' => $assurance?->getCode() ?? ($snapshot['code'] ?? null),
                'logoPath' => $assurance?->getLogoPath() ?? ($snapshot['logoPath'] ?? null),
                'tauxCouverture' => $facture->getCoverageRate(),
                'montantTotal' => $montantTotal,
                'montantAssurance' => $montantAssurance,
                'montantPatient' => $montantPatient,
                'partPatientPayee' => $patientPaid,
                'restePatient' => $restePatient,
                'insuranceStatus' => $facture->getInsuranceStatus(),
                'factureAssuranceId' => $facture->getId(),
            ],
            'assuranceSnapshot' => [
                'code' => $snapshot['code'] ?? $assurance?->getCode(),
                'nom' => $snapshot['nom'] ?? $assurance?->getNom(),
                'logoPath' => $snapshot['logoPath'] ?? $assurance?->getLogoPath(),
                'formData' => is_array($snapshot['formData'] ?? null) ? $snapshot['formData'] : [],
            ],
            'assureFields' => $this->buildAssureFields($assurance?->getFormSchema() ?? [], $snapshot['formData'] ?? []),
            'lignes' => $lines,
            'contenus' => $contenus,
            'hasCabinetServices' => $hasCabinetServices,
            'cabinetServicesFootnote' => $hasCabinetServices
                ? 'Les services marqués « Service cabinet » sont facturés par le cabinet et ne relèvent pas de l\'honoraire du praticien.'
                : null,
            'montant' => $montantTotal,
            'montantTotal' => $montantTotal,
            'montantAssurance' => $montantAssurance,
            'montantPatient' => $montantPatient,
            'partPatientPayee' => $patientPaid,
            'restePatient' => $restePatient,
            'tauxCouverture' => $facture->getCoverageRate(),
            'insuranceStatus' => $facture->getInsuranceStatus(),
            'isRecouvre' => $facture->isRecouvre(),
            'lot' => $lot ? [
                'id' => $lot->getId(),
                'description' => $lot->getDescription(),
                'statut' => $lot->getStatut(),
            ] : null,
            'type' => 'DevisAssurance',
        ];
    }

    /**
     * @param array<string, mixed> $formSchema
     * @param mixed $formData
     * @return list<array{key: string, label: string, value: string}>
     */
    private function buildAssureFields(array $formSchema, mixed $formData): array
    {
        if (!is_array($formData)) {
            $formData = [];
        }

        $fields = $formSchema['fields'] ?? [];
        if (!is_array($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            if ($key === '' || $key === 'coverageRate') {
                continue;
            }

            $raw = $formData[$key] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? $key),
                'value' => is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_UNESCAPED_UNICODE),
            ];
        }

        // Fallback: dump remaining formData keys when schema is empty/outdated
        if ($result === [] && $formData !== []) {
            foreach ($formData as $key => $raw) {
                if (!is_string($key) || $key === 'coverageRate' || $raw === null || $raw === '') {
                    continue;
                }
                $result[] = [
                    'key' => $key,
                    'label' => $key,
                    'value' => is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $result;
    }
}

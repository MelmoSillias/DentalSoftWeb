<?php

namespace App\CareDelivery\Application\Mapper;

use App\CareDelivery\Application\Policy\ConsultationInvoicePolicy;
use App\CareDelivery\Domain\Entity\Consultation;

final class ConsultationInvoiceMapper
{
    public function __construct(private ConsultationInvoicePolicy $policy)
    {
    }

    public function invoice(Consultation $consultation): array
    {
        $assurance = $consultation->getFactureAssurance();
        if ($assurance !== null) {
            $totals = $assurance->computeTotals();
            $paid = $assurance->computePatientPaidAmount();
            $montantPatient = (float) ($totals['montantPatient'] ?? 0.0);
            $montantTotal = (float) ($totals['montantTotal'] ?? 0.0);
            $reste = max(0.0, $montantPatient - $paid);
            $status = $assurance->getInsuranceStatus();
            $isRegle = $status === 'validated_empty' || ($montantTotal > 0.0 && $reste <= 0.0);

            return [
                'factureId' => $assurance->getId(),
                'factureAssuranceId' => $assurance->getId(),
                'factureType' => 'FactureAssurance',
                'factureMontant' => $montantPatient,
                'factureReste' => $reste,
                'factureReglee' => $isRegle,
                'factureInsuranceStatus' => $status,
                'insuranceRate' => (float) ($totals['tauxCouverture'] ?? 0.0),
                'insuranceAmount' => (float) ($totals['montantAssureur'] ?? 0.0),
                'patientPaidAmount' => $paid,
                'factstate' => $isRegle ? 1 : 0,
            ];
        }

        $facture = $consultation->getFacture();
        if ($facture === null) {
            return [
                'factureId' => null,
                'factureAssuranceId' => null,
                'factureType' => null,
                'factureMontant' => null,
                'factureReste' => null,
                'factureReglee' => null,
                'factureInsuranceStatus' => null,
                'insuranceRate' => null,
                'insuranceAmount' => null,
                'patientPaidAmount' => null,
                'factstate' => null,
            ];
        }

        $montants = $facture->computeMontantsFromConsultation();
        $montant = (float) ($montants['montantTotal'] ?? 0.0);
        $reste = (float) ($montants['restePatient'] ?? 0.0);
        $isRegle = $montant > 0.0 ? $reste <= 0.0 : $facture->isReglee();

        return [
            'factureId' => $facture->getId(),
            'factureAssuranceId' => null,
            'factureType' => 'Facture',
            'factureMontant' => $montant,
            'factureReste' => $reste,
            'factureReglee' => $isRegle,
            'factureInsuranceStatus' => null,
            'insuranceRate' => null,
            'insuranceAmount' => null,
            'patientPaidAmount' => max(0.0, $montant - $reste),
            'factstate' => $isRegle ? 1 : 0,
        ];
    }

    public function patientSummary(Consultation $consultation): array
    {
        $facture = $consultation->getFacture();
        $statut = $consultation->getStatut();

        $actes = [];
        foreach ($consultation->getActes() as $acte) {
            $quantite = max(1, (int) ($acte->getQuantite() ?? 1));
            $prix = (float) ($acte->getPrix() ?? 0);
            $actes[] = [
                'id' => $acte->getId(),
                'type' => $acte->getType(),
                'description' => $acte->getDescription(),
                'dent' => $acte->getDent(),
                'prix' => $prix,
                'quantite' => $quantite,
                'montant' => $prix * $quantite,
            ];
        }

        return [
            'id' => $consultation->getId(),
            'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
            'statut' => $statut,
            'state' => $statut,
            'medecin' => $consultation->getMedecin()?->getFullName(),
            'factureMontant' => $facture?->getMontantTotal(),
            'factureStatut' => $facture?->isReglee() ? 1 : 0,
            'factureId' => $facture?->getId(),
            'factModifiable' => $statut === 1 && $this->policy->isModifiable($facture),
            'actes' => $actes,
        ];
    }
}

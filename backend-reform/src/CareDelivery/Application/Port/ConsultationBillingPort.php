<?php

namespace App\CareDelivery\Application\Port;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Assurance;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Facture;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Paiement;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use DateTimeImmutable;

interface ConsultationBillingPort
{
    /**
     * Creates ticket Paiement + Transaction for a classic (non-insured) consultation.
     *
     * @return array{paiement: ?Paiement, error: ?string, status: ?int}
     */
    public function createClassicTicketPayment(
        Consultation $consultation,
        float $patientAmount,
        int $modePaiementId,
        DateTimeImmutable $timestamp,
    ): array;

    /**
     * Attaches a pending FactureAssurance to the consultation (create path).
     */
    public function attachPendingFactureAssurance(
        Consultation $consultation,
        Patient $patient,
        Assurance $assurance,
        float $insuranceRate,
        float $consultationAmount,
        bool $isPayant,
        array $formData,
    ): void;

    /**
     * Ensures insurance or classic invoice exists on consultation closure.
     *
     * @return array{path: 'insurance'|'classic', notifyAmount: float}
     */
    public function ensureInvoicesOnClosure(Consultation $consultation): array;

    /**
     * Removes paiements, transactions, facture assurance and classic facture for a consultation.
     */
    public function cascadeRemoveBillingForConsultation(Consultation $consultation): void;

    public function isFactureModifiable(?Facture $facture): bool;
}

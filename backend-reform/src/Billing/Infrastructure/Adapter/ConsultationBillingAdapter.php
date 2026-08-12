<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Assurance;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Facture;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\FactureAssurance;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\ModeDePaiement;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Paiement;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Transaction;
use App\CareDelivery\Application\Port\ConsultationBillingPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class ConsultationBillingAdapter implements ConsultationBillingPort
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function createClassicTicketPayment(
        Consultation $consultation,
        float $patientAmount,
        int $modePaiementId,
        DateTimeImmutable $timestamp,
    ): array {
        $modePaiement = $this->em->getRepository(ModeDePaiement::class)->find($modePaiementId);

        if (!$modePaiement) {
            return [
                'paiement' => null,
                'error' => 'Mode de paiement invalide.',
                'status' => 400,
            ];
        }

        $paiement = new Paiement();
        $paiement->setFacture(null);
        $paiement->setMode($modePaiement);
        $paiement->setMontant($patientAmount);
        $paiement->setDate($timestamp);
        $paiement->setConsultation($consultation);
        $this->em->persist($paiement);

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) $patientAmount);
        $transaction->setDateTransaction($timestamp);
        $transaction->setDescription('Ticket de consultation #' . $consultation->getId() . ' | Part patient');
        $transaction->setModeDePaiement($modePaiement);
        $transaction->setConsultation($consultation);
        $transaction->markValidated($timestamp);
        $transaction->setPaiement($paiement);
        $this->em->persist($transaction);

        return [
            'paiement' => $paiement,
            'error' => null,
            'status' => null,
        ];
    }

    public function attachPendingFactureAssurance(
        Consultation $consultation,
        Patient $patient,
        Assurance $assurance,
        float $insuranceRate,
        float $consultationAmount,
        bool $isPayant,
        array $formData,
    ): void {
        $this->attachFactureAssurance(
            $consultation,
            $patient,
            $assurance,
            $insuranceRate,
            $consultationAmount,
            $isPayant,
            $formData,
            'pending',
        );
    }

    public function ensureInvoicesOnClosure(Consultation $consultation): array
    {
        $factureAssurance = $this->ensureFactureAssurance($consultation);

        if ($factureAssurance !== null) {
            $factureAssurance->setInsuranceStatus('ready');
            $this->em->persist($factureAssurance);
            $this->em->flush();

            return [
                'path' => 'insurance',
                'notifyAmount' => (float) ($factureAssurance->computeTotals()['montantPatient'] ?? 0.0),
            ];
        }

        $isNewFacture = !$consultation->getFacture();
        $facture = $consultation->getFacture() ?? new Facture();
        $facture->setConsultation($consultation);
        if ($isNewFacture) {
            $facture->setDateFacture($this->resolveFactureDateFromConsultation($consultation));
        }

        $montants = $facture->computeMontantsFromConsultation();
        $facture->setIsReglee(((float) ($montants['restePatient'] ?? 0.0)) <= 0.0);

        $consultation->setFacture($facture);
        $this->em->persist($facture);
        $this->em->flush();

        return [
            'path' => 'classic',
            'notifyAmount' => (float) ($montants['montantTotal'] ?? 0.0),
        ];
    }

    public function cascadeRemoveBillingForConsultation(Consultation $consultation): void
    {
        /** @var Facture|null $facture */
        $facture = $consultation->getFacture();

        $paiementConsultation = $consultation->getPaiement();
        if ($paiementConsultation) {
            $transaction = $paiementConsultation->getTransaction();
            if ($transaction) {
                $transaction->setPaiement(null);
                $this->em->remove($transaction);
            }
            $paiementConsultation->setConsultation(null);
            $paiementConsultation->setFacture(null);
            $this->em->remove($paiementConsultation);
            $this->em->flush();
        }

        $factureAssurance = $consultation->getFactureAssurance();
        if ($factureAssurance) {
            foreach ($factureAssurance->getPaiements() as $paiement) {
                $transaction = $paiement->getTransaction();
                if ($transaction) {
                    $transaction->setPaiement(null);
                    $this->em->remove($transaction);
                }
                $paiement->setFactureAssurance(null);
                $this->em->remove($paiement);
            }
            $this->em->remove($factureAssurance);
            $this->em->flush();
        }

        if ($facture) {
            foreach ($facture->getPaiements() as $paiement) {
                $transaction = $paiement->getTransaction();
                if ($transaction) {
                    $transaction->setPaiement(null);
                    $this->em->remove($transaction);
                }
                $paiement->setFacture(null);
                $this->em->remove($paiement);
            }

            $consultation->setFacture(null);
            $facture->setConsultation(null);
            $this->em->remove($facture);
            $this->em->flush();
        }
    }

    public function isFactureModifiable(?Facture $facture): bool
    {
        if (!$facture) {
            return false;
        }

        if ($facture->getPaiements()->count() > 0) {
            return false;
        }

        $consultation = $facture->getConsultation();
        $factureAssurance = $consultation?->getFactureAssurance();
        if ($factureAssurance === null) {
            return true;
        }

        $lot = $factureAssurance->getLotFactureAssurance();
        if ($lot !== null) {
            $statut = $lot->getStatut() === 'recouvre' ? 'rembourse' : $lot->getStatut();
            if (in_array($statut, ['envoye', 'confirme', 'partiellement_rembourse', 'rembourse'], true)) {
                return false;
            }
        }

        return $factureAssurance->computePatientPaidAmount() <= 0;
    }

    /**
     * Creates FactureAssurance when the patient is insured and none exists yet
     * (e.g. consultation créée via RDV, ou créée avant le profil assurance).
     */
    private function ensureFactureAssurance(Consultation $consultation): ?FactureAssurance
    {
        $existing = $consultation->getFactureAssurance();
        if ($existing !== null) {
            return $existing;
        }

        $patient = $consultation->getPatient();
        if (!$this->patientHasActiveInsurance($patient)) {
            return null;
        }

        $profile = $patient->getAssuranceProfile();
        $assurance = $profile?->getAssurance();
        if (!$assurance instanceof Assurance) {
            return null;
        }

        // Ticket déjà encaissé hors assurance : ne pas re-facturer la consultation dans la FA.
        // Les actes restent couverts via buildActeLignes().
        return $this->attachFactureAssurance(
            $consultation,
            $patient,
            $assurance,
            max(0, min(100, (float) ($profile?->getCoverageRate() ?? 0))),
            0.0,
            false,
            $profile?->getFormData() ?? [],
            'pending',
        );
    }

    private function attachFactureAssurance(
        Consultation $consultation,
        Patient $patient,
        Assurance $assurance,
        float $insuranceRate,
        float $consultationAmount,
        bool $isConsultationPayante,
        array $formData,
        string $insuranceStatus = 'pending',
    ): FactureAssurance {
        $factureAssurance = new FactureAssurance();
        $factureAssurance
            ->setConsultation($consultation)
            ->setPatient($patient)
            ->setAssurance($assurance)
            ->setCoverageRate($insuranceRate > 0 ? $insuranceRate : null)
            ->setDateFacture(new DateTime())
            ->setConsultationAmount($isConsultationPayante ? $consultationAmount : 0.0)
            ->setIsConsultationPayante($isConsultationPayante)
            ->setInsuranceStatus($insuranceStatus)
            ->setAssuranceSnapshot([
                'code' => $assurance->getCode(),
                'nom' => $assurance->getNom(),
                'logoPath' => $assurance->getLogoPath(),
                'website' => $assurance->getWebsite(),
                'email' => $assurance->getEmail(),
                'formData' => $formData,
            ]);

        $consultation->setFactureAssurance($factureAssurance);
        $this->em->persist($factureAssurance);

        return $factureAssurance;
    }

    private function resolveFactureDateFromConsultation(Consultation $consultation): DateTime
    {
        $createdAt = $consultation->getCreatedAt();

        return $createdAt instanceof \DateTimeInterface
            ? DateTime::createFromInterface($createdAt)
            : new DateTime('now');
    }

    private function patientHasActiveInsurance(?Patient $patient): bool
    {
        $profile = $patient?->getAssuranceProfile();
        $assurance = $profile?->getAssurance();

        return $profile !== null && $assurance !== null && $assurance->isActif();
    }
}

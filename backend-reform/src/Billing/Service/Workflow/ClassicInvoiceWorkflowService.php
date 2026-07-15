<?php

namespace App\Billing\Service\Workflow;

use App\Billing\Entity\Devis;
use App\Billing\Entity\Facture;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\FactureRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\PaiementRepository;
use App\CareDelivery\Entity\Consultation;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class ClassicInvoiceWorkflowService
{
    public function __construct(
        private FactureRepository $factureRepo,
        private DevisRepository $devisRepo,
        private PaiementRepository $paiementRepo,
        private ModeDePaiementRepository $modeRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function listFacturesByPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $factures = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.factureAssurance', 'fa')->addSelect('fa')
            ->andWhere('f.dateFacture BETWEEN :start AND :end')
            ->andWhere('c.statut = 1')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Facture $facture): array => $this->mapFactureToArray($facture), $factures);
    }

    public function listFacturesImpayees(?DateTimeInterface $start = null, ?DateTimeInterface $end = null): array
    {
        $factures = $this->factureRepo->findUnpaidClassicFactures($start, $end);
        $this->prefetchFactureListRelations($factures);

        return array_map(fn (Facture $facture): array => $this->mapFactureToArray($facture, true, false), $factures);
    }

    public function listFacturesImpayeesByPatient(int $patientId): array
    {
        $factures = $this->factureRepo->findUnpaidClassicFactures(null, null, $patientId);
        $this->prefetchFactureListRelations($factures);

        return array_map(fn (Facture $facture): array => $this->mapFactureToArray($facture, true, false), $factures);
    }

    public function listFacturesByPatient(int $patientId): array
    {
        $factures = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.factureAssurance', 'fa')->addSelect('fa')
            ->andWhere('p.id = :patientId')
            ->andWhere('c.statut = 1')
            ->setParameter('patientId', $patientId)
            ->orderBy('f.dateFacture', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Facture $facture): array => $this->mapFactureToArray($facture), $factures);
    }

    public function previewDevis(int $id): ?array
    {
        $devis = $this->devisRepo->find($id);
        if (!$devis instanceof Devis) {
            return null;
        }

        $patient = $devis->getFicheMedicale()?->getPatient();
        $contenus = [];
        $montant = 0.0;

        foreach ($devis->getContenus() as $contenu) {
            $lineTotal = $contenu->getQte() * $contenu->getMontant();
            $montant += $lineTotal;
            $contenus[] = [
                'designation' => $contenu->getDesignation(),
                'qte' => $contenu->getQte(),
                'montant' => $contenu->getMontant(),
                'total' => $lineTotal,
            ];
        }

        return [
            'id' => $devis->getId(),
            'date' => $devis->getDate()?->format('Y-m-d'),
            'description' => $devis->getDescription(),
            'montant' => $montant > 0 ? $montant : (float) ($devis->getMontant() ?? 0.0),
            'reste' => (float) ($devis->getReste() ?? $montant),
            'statut' => $devis->getStatut() ?? 0,
            'patient' => [
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'telephone' => $patient?->getTelephone(),
            'contenus' => $contenus,
            'type' => 'Devis',
        ];
    }

    public function previewFactureDetail(int $id): ?array
    {
        $facture = $this->factureRepo->find($id);
        if (!$facture) {
            return null;
        }

        return $this->mapFactureToArray($facture, true);
    }

    public function previewFacture(int $id): ?array
    {
        $facture = $this->factureRepo->find($id);
        if (!$facture) {
            return null;
        }

        return $this->mapFactureToArray($facture, true);
    }

    public function payerFacture(int $id, array $payload = []): array
    {
        $facture = $this->factureRepo->find($id);
        if (!$facture) {
            return ['error' => 'Facture introuvable'];
        }

        $consultation = $facture->getConsultation();
        if (!$consultation instanceof Consultation) {
            return ['error' => 'Consultation introuvable'];
        }

        if ($consultation->getFactureAssurance() !== null) {
            return ['error' => 'Cette consultation est couverte par une assurance. Utilisez le module assurance pour encaisser la part patient.'];
        }

        $modeId = (int) ($payload['modeId'] ?? 0);
        $montant = (float) ($payload['montant'] ?? 0);
        $date = $payload['date'] ?? null;
        $time = $payload['time'] ?? null;

        $timestamp = new \DateTime();
        if (!empty($date) && !empty($time)) {
            try {
                $timestamp = new \DateTime($date . ' ' . $time);
            } catch (\Exception) {
                $timestamp = new \DateTime();
            }
        }

        $montants = $facture->computeMontantsFromConsultation();
        $remaining = max(0.0, (float) ($montants['restePatient'] ?? 0.0));

        if ($remaining <= 0.0) {
            $facture->setIsReglee(true);
            $this->em->persist($facture);
            $this->em->flush();
            return ['success' => true];
        }

        $mode = $this->modeRepo->find($modeId);
        if (!$mode || $montant <= 0 || $montant > $remaining) {
            return ['error' => 'Données invalides'];
        }

        $paiement = new Paiement();
        $facture->addPaiement($paiement);
        $paiement->setMode($mode);
        $paiement->setMontant($montant);
        $paiement->setDate($timestamp);

        $patientName = $consultation->getFicheMedicale()?->getPatient()?->getFullName() ?? '';

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant($paiement->getMontant());
        $transaction->setDateTransaction($timestamp);
        $transaction->setDescription('Paiement Facture #' . $facture->getId() . ' | ' . $patientName);
        $transaction->setModeDePaiement($mode);
        $transaction->markValidated(\DateTimeImmutable::createFromMutable($timestamp));
        $transaction->setPaiement($paiement);

        $this->em->persist($transaction);
        $this->em->persist($paiement);

        $montants = $facture->computeMontantsFromConsultation();
        $remainingAfter = max(0.0, (float) ($montants['restePatient'] ?? 0.0));

        $facture->setIsReglee($remainingAfter <= 0.0);

        $this->em->persist($facture);
        $this->em->flush();

        return ['success' => true, 'paiement_id' => $paiement->getId()];
    }

    public function resetFacturePayments(int $id): array
    {
        $facture = $this->factureRepo->find($id);
        if (!$facture) {
            return ['error' => 'Facture introuvable'];
        }

        $allPaiements = $this->paiementRepo->createQueryBuilder('p')
            ->where('p.facture = :facture')
            ->setParameter('facture', $facture)
            ->getQuery()
            ->getResult();

        foreach ($allPaiements as $paiement) {
            $transaction = $paiement->getTransaction();
            if ($transaction) {
                $transaction->setPaiement(null);
                $this->em->remove($transaction);
            }
            $paiement->setFacture(null);
            $this->em->remove($paiement);
        }

        $facture->setIsReglee(false);

        $this->em->persist($facture);
        $this->em->flush();

        return ['success' => true];
    }

    public function mapFactureToArray(Facture $facture, bool $forceFacture = true, bool $includeDetails = true): array
    {
        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient();
        $montants = $facture->computeMontantsFromConsultation();
        $reste = $forceFacture ? (float) ($montants['restePatient'] ?? 0.0) : 0.0;
        $displayMontant = (float) ($montants['montantTotal'] ?? 0.0);
        $isRegle = $displayMontant > 0.0
            ? $reste <= 0.0
            : $facture->isReglee();

        $contenus = [];
        if ($includeDetails) {
            $contenus = $facture->buildLignesFromConsultation();
        }

        return [
            'id' => $facture->getId(),
            'date' => $facture->getDateFacture()?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d'),
            'consultation' => $consultation?->getId(),
            'montant' => $displayMontant,
            'montantTotal' => (float) ($montants['montantTotal'] ?? 0.0),
            'montantPatient' => (float) ($montants['montantPatient'] ?? $displayMontant),
            'montantAssureur' => 0.0,
            'reste' => $reste,
            'statut' => $isRegle ? 1 : 0,
            'isRegle' => $isRegle,
            'hasPayments' => $facture->getPaiements()->count() > 0,
            'patient' => [
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'telephone' => $patient?->getTelephone(),
            'contenus' => $contenus,
            'paiements' => $includeDetails ? $this->buildFacturePaymentDetails($facture) : [],
            'type' => 'Facture',
            'insurance' => ['hasInsurance' => false, 'insuranceStatus' => 'none'],
        ];
    }

    /**
     * @param Facture[] $factures
     */
    private function prefetchFactureListRelations(array $factures): void
    {
        if ($factures === []) {
            return;
        }

        $consultationIds = [];
        $factureIds = [];

        foreach ($factures as $facture) {
            if (!$facture instanceof Facture) {
                continue;
            }

            $factureIds[] = $facture->getId();
            $consultationId = $facture->getConsultation()?->getId();
            if ($consultationId !== null) {
                $consultationIds[] = $consultationId;
            }
        }

        if ($consultationIds !== []) {
            $this->em->createQueryBuilder()
                ->select('c', 'a', 'fa')
                ->from(Consultation::class, 'c')
                ->leftJoin('c.actes', 'a')
                ->leftJoin('c.factureAssurance', 'fa')
                ->where('c.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($consultationIds)))
                ->getQuery()
                ->getResult();
        }

        if ($factureIds !== []) {
            $this->em->createQueryBuilder()
                ->select('f', 'pay', 'pt')
                ->from(Facture::class, 'f')
                ->leftJoin('f.paiements', 'pay')
                ->leftJoin('pay.transaction', 'pt')
                ->where('f.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($factureIds)))
                ->getQuery()
                ->getResult();
        }
    }

    private function buildFacturePaymentDetails(Facture $facture): array
    {
        $details = [];

        $payments = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.mode', 'm')->addSelect('m')
            ->leftJoin('p.transaction', 't')->addSelect('t')
            ->where('p.facture = :facture')
            ->setParameter('facture', $facture)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($payments as $payment) {
            $transaction = $payment->getTransaction();
            $details[] = [
                'id' => $payment->getId(),
                'sourceType' => 'payment',
                'mode' => $payment->getMode()?->getLibelle(),
                'modeId' => $payment->getMode()?->getId(),
                'montant' => $payment->getMontant(),
                'date' => $payment->getDate()?->format('Y-m-d H:i:s'),
                'status' => $transaction?->getValidationStatus() ?? 'validated',
                'description' => $transaction?->getDescription(),
            ];
        }

        usort($details, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['date'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['date'] ?? '')) ?: 0;

            if ($leftTime === $rightTime) {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            }

            return $rightTime <=> $leftTime;
        });

        return $details;
    }
}

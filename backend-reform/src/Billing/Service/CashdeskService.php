<?php

namespace App\Billing\Service;

use App\Billing\Entity\Devis;
use App\Billing\Entity\Facture;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\AssuranceRepository;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\FactureRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\PaiementRepository;
use App\Billing\Repository\TransactionRepository;
use App\CareDelivery\Entity\Consultation;
use App\Patient\Entity\Patient;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class CashdeskService
{
    public function __construct(
        private FactureRepository $factureRepo,
        private DevisRepository $devisRepo,
        private FactureAssuranceRepository $factureAssuranceRepo,
        private AssuranceRepository $assuranceRepo,
        private PaiementRepository $paiementRepo,
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function listFacturesByPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $factures = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.factureAssurance', 'fa')
            ->andWhere('fa.id IS NULL')
            ->andWhere('f.dateFacture BETWEEN :start AND :end')
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
            ->leftJoin('c.factureAssurance', 'fa')
            ->andWhere('fa.id IS NULL')
            ->andWhere('p.id = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('f.dateFacture', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Facture $facture): array => $this->mapFactureToArray($facture), $factures);
    }

    public function listPaiementsFactures(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->leftJoin('p.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'fpat')->addSelect('fpat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Paiement $p) {
            $patient = $p->getConsultation()?->getPatient() ?? $p->getFacture()?->getConsultation()?->getPatient();
            $factureId = $p->getFacture()?->getId();
            $consultationId = $p->getFacture()?->getConsultation()?->getId() ?? $p->getConsultation()?->getId();

            return [
                'factureId' => $factureId ?? $p->getId(),
                'consultationId' => $consultationId,
                'patient' => $patient ? $patient->getFullName() : 'Anonyme',
                'telephone' => $patient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'modeId' => $p->getMode()->getId(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $factureId ? 'facture' : 'ticket',
                'pId' => $p->getId(),
            ];
        }, $paiements);
    }

    public function listPaiementsByPatients(Patient $patient): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->leftJoin('p.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'fpat')->addSelect('fpat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('pat = :patient OR fpat = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Paiement $p) {
            $resolvedPatient = $p->getConsultation()?->getPatient() ?? $p->getFacture()?->getConsultation()?->getPatient();
            $factureId = $p->getFacture()?->getId();
            $consultationId = $p->getFacture()?->getConsultation()?->getId() ?? $p->getConsultation()?->getId();

            return [
                'factureId' => $factureId ?? $p->getId(),
                'consultationId' => $consultationId,
                'patient' => $resolvedPatient ? $resolvedPatient->getFullName() : 'Anonyme',
                'telephone' => $resolvedPatient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $factureId ? 'facture' : 'ticket',
                'pId' => $p->getId(),
            ];
        }, $paiements);
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

    private function consultationHasValidatedPatientPayment(Consultation $consultation): bool
    {
        $payment = $consultation->getPaiement();
        if ($payment === null) {
            return false;
        }
        $status = $payment->getTransaction()?->getValidationStatus();
        if ($status === null || $status === 'validated') {
            return true;
        }

        return false;
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
            return ['error' => 'Cette facture assurance doit etre reglee depuis le workflow assurances'];
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

        $updatedMontants = $facture->computeMontantsFromConsultation();
        $remaining = max(0.0, (float) ($updatedMontants['restePatient'] ?? 0.0));

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

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant($paiement->getMontant());
        $transaction->setDateTransaction($timestamp);
        $transaction->setDescription('Paiement Facture #' . $facture->getId() . ' | '. $consultation->getFicheMedicale()->getPatient()->getFullName());
        $transaction->setModeDePaiement($mode); 
        $transaction->markValidated(\DateTimeImmutable::createFromMutable($timestamp));
        $transaction->setPaiement($paiement);

        $this->em->persist($transaction);
        $this->em->persist($paiement);

        $updatedMontants = $facture->computeMontantsFromConsultation();
        $remainingAfter = max(0.0, (float) ($updatedMontants['restePatient'] ?? 0.0));

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

        $consultation = $facture->getConsultation();
        if (!$consultation instanceof Consultation) {
            return ['error' => 'Consultation introuvable'];
        }

        $allPaiements = $this->paiementRepo->createQueryBuilder('p')
            ->where('p.facture = :facture')
            ->setParameter('facture', $facture)
            ->getQuery()
            ->getResult();

        $paiementIds = array_values(array_filter(array_map(
            static fn (Paiement $paiement): ?int => $paiement->getId(),
            $allPaiements
        )));

        $txQb = $this->transactionRepo->createQueryBuilder('t')
            ->where('1 = 0');

        if (!empty($paiementIds)) {
            $txQb->orWhere('t.paiement IN (:paiementIds)')
                ->setParameter('paiementIds', $paiementIds);
        }

        $txQb->orWhere('t.consultation = :consultation AND t.rolePaiement = :roleInsurance')
            ->setParameter('consultation', $consultation)
            ->setParameter('roleInsurance', 'insurance');

        $transactions = $txQb->getQuery()->getResult();

        foreach ($transactions as $transaction) {
            $paiement = $transaction->getPaiement();
            if ($paiement instanceof Paiement && !in_array($paiement, $allPaiements, true)) {
                $allPaiements[] = $paiement;
            }

            $transaction->setPaiement(null);
            $transaction->setFacture(null);
            $transaction->setConsultation(null);
            $this->em->remove($transaction);
        }

        foreach ($allPaiements as $paiement) {
            $paiement->setFacture(null);
            $paiement->setConsultation(null);
            $this->em->remove($paiement);
        }

        $facture->setIsReglee(false); 

        $this->em->persist($facture);
        $this->em->flush();

        return ['success' => true];
    }

    private function mapFactureToArray(Facture $facture, bool $forceFacture = true, bool $includeDetails = true): array
    {
        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient();
        $factureAssurance = $consultation?->getFactureAssurance();
        $montants = $this->resolveFactureMontants($facture);
        $reste = $forceFacture ? (float) $montants['restePatient'] : 0.0;
        $isRegle = ((float) ($montants['montantTotal'] ?? 0.0)) > 0.0
            ? $reste <= 0.0
            : $facture->isReglee();

        return [
            'id' => $facture->getId(),
            'date' => $facture->getDateFacture()?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d'),
            'consultation' => $consultation?->getId(),
            'montant' => (float) $montants['montantTotal'],
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
            'contenus' => $includeDetails ? $facture->buildLignesFromConsultation() : [],
            'paiements' => $includeDetails ? $this->buildFacturePaymentDetails($facture) : [],
            'type' => 'Facture',
            'insurance' => $includeDetails
                ? $this->buildFactureInsuranceMetadata($facture, $montants)
                : ($factureAssurance !== null
                    ? ['hasInsurance' => true, 'insuranceStatus' => $factureAssurance->getInsuranceStatus()]
                    : ['hasInsurance' => false, 'insuranceStatus' => 'none']),
        ];
    }

    private function resolveFactureMontants(Facture $facture): array
    {
        $consultation = $facture->getConsultation();
        $factureAssurance = $consultation?->getFactureAssurance();

        if ($factureAssurance !== null) {
            $totals = $factureAssurance->computeTotals();
            $patientAmount = (float) ($totals['montantPatient'] ?? 0.0);
            $patientPaid = $this->resolveInsurancePatientPaidAmount($consultation, $facture);

            return [
                'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
                'montantPatient' => $patientAmount,
                'montantAssureur' => (float) ($totals['montantAssureur'] ?? 0.0),
                'patientPaid' => $patientPaid,
                'restePatient' => max(0.0, $patientAmount - $patientPaid),
            ];
        }

        return $facture->computeMontantsFromConsultation();
    }

    private function resolveInsurancePatientPaidAmount(?Consultation $consultation, Facture $facture): float
    {
        $paid = (float) $facture->computePatientPaidAmount();

        if ($consultation) {
            $paid += (float) $this->transactionRepo->createQueryBuilder('t')
                ->select('COALESCE(SUM(t.montant), 0)')
                ->where('t.consultation = :consultation')
                ->andWhere('t.rolePaiement = :role')
                ->andWhere('t.validationStatus = :status')
                ->setParameter('consultation', $consultation)
                ->setParameter('role', 'patient_insurance')
                ->setParameter('status', 'validated')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return max(0.0, $paid);
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

    private function buildFactureInsuranceMetadata(Facture $facture, ?array $montants = null): array
    {
        $consultation = $facture->getConsultation();
        $montants ??= $this->resolveFactureMontants($facture);
        $patientPaidAmount = (float) ($montants['patientPaid'] ?? 0.0);

        $factureAssurance = $consultation?->getFactureAssurance();
        if ($factureAssurance !== null) {
            $totals = $factureAssurance->computeTotals();
            $insuranceAmount = (float) ($totals['montantAssureur'] ?? 0.0);
            $insuranceRate = $factureAssurance->getCoverageRate();

            $insurancePaidAmount = $factureAssurance->isRecouvre()
                ? $insuranceAmount
                : (float) $this->transactionRepo->createQueryBuilder('t')
                    ->select('COALESCE(SUM(t.montant), 0)')
                    ->where('t.consultation = :consultation')
                    ->andWhere('t.rolePaiement IN (:roles)')
                    ->andWhere('t.validationStatus = :status')
                    ->setParameter('consultation', $consultation)
                    ->setParameter('roles', ['insurance', 'insurance_lot'])
                    ->setParameter('status', 'validated')
                    ->getQuery()
                    ->getSingleScalarResult();

            $insurancePendingAmount = max(0.0, $insuranceAmount - $insurancePaidAmount);
            $lot = $factureAssurance->getLotFactureAssurance();

            return [
                'hasInsurance' => true,
                'insuranceStatus' => $factureAssurance->getInsuranceStatus(),
                'assuranceId' => $factureAssurance->getAssurance()?->getId(),
                'assuranceCode' => $factureAssurance->getAssurance()?->getCode(),
                'insuranceModeLabel' => $factureAssurance->getAssurance()?->getNom(),
                'insuranceRate' => $insuranceRate,
                'insuranceAmount' => $insuranceAmount,
                'montantPatient' => (float) ($totals['montantPatient'] ?? 0.0),
                'montantAssurance' => $insuranceAmount,
                'insurancePaidAmount' => $insurancePaidAmount,
                'insurancePendingAmount' => $insurancePendingAmount,
                'insuranceTransactionId' => null,
                'insurancePaymentId' => null,
                'lotId' => $lot?->getId(),
                'lotStatut' => $lot?->getStatut(),
                'lotDescription' => $lot?->getDescription(),
                'patientPaidAmount' => $patientPaidAmount,
                'patientRemainingAmount' => max(0.0, (float) ($montants['restePatient'] ?? 0.0)),
            ];
        }

        return [
            'hasInsurance' => false,
            'insuranceStatus' => 'none',
            'assuranceId' => null,
            'insuranceModeLabel' => null,
            'insuranceRate' => null,
            'insuranceAmount' => 0.0,
            'insurancePaidAmount' => 0.0,
            'insurancePendingAmount' => 0.0,
            'insuranceTransactionId' => null,
            'insurancePaymentId' => null,
            'patientPaidAmount' => $patientPaidAmount,
            'patientRemainingAmount' => max(0.0, (float) ($montants['restePatient'] ?? 0.0)),
        ];
    }

    public function paiementsForPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function paiementById(int $id): ?Paiement
    {
        return $this->paiementRepo->find($id);
    }

    public function getCaissePageContext(): array
    {
        return [
            'modesPaiement' => $this->modeRepo->findAll(),
        ];
    }

    public function mapPaiementReceipt(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();
        $consultation = $paiement->getConsultation();
        $assuranceBlock = $this->buildAssurancePrintBlock($consultation?->getFactureAssurance());

        $payload = [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'devis' => $factureId ? [
                'id' => $factureId,
                'total' => $paiement->getFacture()->computeMontantsFromConsultation()['montantTotal'] ?? 0.0,
                'reste' => $paiement->getFacture()->computeMontantsFromConsultation()['restePatient'] ?? 0.0,
                'fiche' => [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ],
            ] : null,
        ];

        if ($assuranceBlock !== null) {
            $payload['assurance'] = $assuranceBlock;
            if (!$payload['devis'] && $patient) {
                $payload['devis'] = [
                    'id' => $consultation?->getFactureAssurance()?->getId(),
                    'total' => $assuranceBlock['montantTotal'],
                    'reste' => $assuranceBlock['restePatient'],
                    'fiche' => [
                        'patient' => [
                            'nom' => $patient->getNom(),
                            'prenom' => $patient->getPrenom(),
                        ],
                    ],
                ];
            }
        }

        return $payload;
    }

    public function mapPaiementTicket(Paiement $paiement): array
    {
        $patient = $paiement?->getConsultation()?->getPatient();
        $assuranceBlock = $this->buildAssurancePrintBlock($paiement->getConsultation()?->getFactureAssurance());

        $payload = [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'consultation' => $paiement->getConsultation() ? [
                'patient' => $patient ? [
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
            ] : null,
        ];

        if ($assuranceBlock !== null) {
            $payload['assurance'] = $assuranceBlock;
        }

        return $payload;
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
        $lines = [];

        foreach ($facture->buildLignes() as $line) {
            $lines[] = [
                'designation' => $line['designation'] ?? 'Soin',
                'description' => $line['description'] ?? '',
                'quantite' => $line['quantite'] ?? 1,
                'prix' => $line['prix'] ?? 0,
                'total' => $line['total'] ?? 0,
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
            'assurance' => $this->buildAssurancePrintBlock($facture),
            'lignes' => $lines,
            'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
            'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
            'montantPatient' => (float) ($totals['montantPatient'] ?? 0.0),
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

    private function buildAssurancePrintBlock(?FactureAssurance $factureAssurance): ?array
    {
        if (!$factureAssurance instanceof FactureAssurance) {
            return null;
        }

        $totals = $factureAssurance->computeTotals();
        $consultation = $factureAssurance->getConsultation();
        $patientPaid = 0.0;

        if ($consultation) {
            $patientPaid = (float) $this->transactionRepo->createQueryBuilder('t')
                ->select('COALESCE(SUM(t.montant), 0)')
                ->where('t.consultation = :consultation')
                ->andWhere('t.rolePaiement = :role')
                ->andWhere('t.validationStatus = :status')
                ->setParameter('consultation', $consultation)
                ->setParameter('role', 'patient_insurance')
                ->setParameter('status', 'validated')
                ->getQuery()
                ->getSingleScalarResult();
        }

        $montantPatient = (float) ($totals['montantPatient'] ?? 0.0);

        return [
            'nom' => $factureAssurance->getAssurance()?->getNom(),
            'code' => $factureAssurance->getAssurance()?->getCode(),
            'tauxCouverture' => $factureAssurance->getCoverageRate(),
            'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
            'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
            'montantPatient' => $montantPatient,
            'partPatientPayee' => $patientPaid,
            'restePatient' => max(0.0, $montantPatient - $patientPaid),
            'insuranceStatus' => $factureAssurance->getInsuranceStatus(),
            'factureAssuranceId' => $factureAssurance->getId(),
        ];
    }

    public function mapPaiementListItem(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();

        return [
            'facture' => $factureId ? [
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

    public function resolvePatientFromPaiement(?Paiement $paiement): ?Patient
    {
        if (!$paiement instanceof Paiement) {
            return null;
        }

        $facture = $paiement->getFacture();
        $fromFicheMedicale = $facture?->getConsultation()?->getFicheMedicale()?->getPatient();
        if ($fromFicheMedicale instanceof Patient) {
            return $fromFicheMedicale;
        }

        $fiche = $facture?->getConsultation()?->getFicheMedicale();
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


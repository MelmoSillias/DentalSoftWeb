<?php

namespace App\Billing\Service;

use App\Billing\Dto\InsuranceClaimLineDto;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use Doctrine\ORM\EntityManagerInterface;

class InsuranceClaimService
{
    public function __construct(
        private FactureAssuranceRepository $factureAssuranceRepository,
        private ModeDePaiementRepository $modeRepository,
        private EntityManagerInterface $em,
        private LotFactureAssuranceService $lotService,
    ) {
    }

    public function listClaims(
        ?string $status = null,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
    ): array
    {
        $qb = $this->factureAssuranceRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('f.assurance', 'a')->addSelect('a')
            ->leftJoin('f.lotFactureAssurance', 'l')->addSelect('l')
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('f.insuranceStatus = :status')
                ->setParameter('status', $status);
        }

        if ($start !== null) {
            $qb->andWhere('f.dateFacture >= :start')
                ->setParameter('start', $start);
        }

        if ($end !== null) {
            $qb->andWhere('f.dateFacture <= :end')
                ->setParameter('end', $end);
        }

        $patientQuery = trim((string) $patientQuery);
        if ($patientQuery !== '') {
            $normalized = '%' . mb_strtolower($patientQuery) . '%';
            $qb
                ->andWhere('LOWER(CONCAT(COALESCE(p.nom, \'\'), \' \', COALESCE(p.prenom, \'\'))) LIKE :patientQuery OR LOWER(COALESCE(p.telephone, \'\')) LIKE :patientQuery')
                ->setParameter('patientQuery', $normalized);
        }

        $assuranceCode = strtoupper(trim((string) $assuranceCode));
        if ($assuranceCode !== '' && $assuranceCode !== 'ALL') {
            $qb->andWhere('UPPER(COALESCE(a.code, \'\')) = :assuranceCode')
                ->setParameter('assuranceCode', $assuranceCode);
        }

        return array_map(fn (FactureAssurance $facture): array => $this->mapClaim($facture), $qb->getQuery()->getResult());
    }

    public function listClaimsWithUnpaidPatient(): array
    {
        $claims = $this->factureAssuranceRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('f.assurance', 'a')->addSelect('a')
            ->leftJoin('f.lotFactureAssurance', 'l')->addSelect('l')
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($claims as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $mapped = $this->mapClaim($facture);
            if ((float) ($mapped['restePatient'] ?? 0.0) > 0) {
                $result[] = $mapped;
            }
        }

        return $result;
    }

    public function getClaimDetail(int $factureId): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        $claim = $this->mapClaim($facture, true);
        $claim['paiements'] = $this->resolveClaimPayments($facture);

        return ['data' => $claim];
    }

    public function validateClaim(int $factureId): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        if ($facture->getInsuranceStatus() === 'recouvre') {
            return ['error' => 'Créance déjà recouvrée', 'status' => 400];
        }

        $facture->setInsuranceStatus('validated');
        $this->lotService->autoAssignClaimToOpenLot($facture);
        $this->em->flush();

        return ['success' => true];
    }

    public function rejectClaim(int $factureId, ?string $reason = null): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        if ($facture->isRecouvre()) {
            return ['error' => 'Impossible de rejeter une créance recouvrée', 'status' => 400];
        }

        $lot = $facture->getLotFactureAssurance();
        if ($lot && $lot->getStatut() !== 'ouvert') {
            return ['error' => 'Impossible de rejeter une facture dans un lot envoye ou recouvre', 'status' => 400];
        }

        if ($lot && $lot->getStatut() === 'ouvert') {
            $lot->removeFactureAssurance($facture);
        }

        $facture->setInsuranceStatus('rejected');
        if ($reason !== null && trim($reason) !== '') {
            $snapshot = $facture->getAssuranceSnapshot();
            $snapshot['rejectReason'] = trim($reason);
            $facture->setAssuranceSnapshot($snapshot);
        }

        $this->em->flush();

        return ['success' => true];
    }

    public function recoverClaim(int $factureId, int $modeId, ?\DateTimeInterface $date = null): array
    {
        return [
            'error' => 'Le recouvrement assurance se fait desormais par lot. Utilisez le workflow lots.',
            'status' => 410,
        ];
    }

    public function payPatientShare(int $factureId, int $modeId, ?float $amount = null, ?\DateTimeInterface $date = null): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        $mode = $this->modeRepository->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $consultation = $facture->getConsultation();
        if (!$consultation) {
            return ['error' => 'Consultation introuvable', 'status' => 400];
        }

        $patientTotal = (float) ($facture->computeTotals()['montantPatient'] ?? 0.0);
        $alreadyPaid = $this->resolvePatientPaidAmount($facture);
        $remaining = max(0.0, $patientTotal - $alreadyPaid);

        if ($remaining <= 0.0) {
            return ['error' => 'La part patient est deja totalement encaissee', 'status' => 400];
        }

        $amountToPay = $amount === null ? $remaining : (float) $amount;
        if ($amountToPay <= 0) {
            return ['error' => 'Montant patient invalide', 'status' => 400];
        }

        if ($amountToPay > $remaining) {
            return ['error' => 'Le montant depasse le reste patient', 'status' => 400];
        }

        $dateTransaction = $date ?? new \DateTimeImmutable();

        $paiement = new Paiement();
        $paiement->setMode($mode);
        $paiement->setMontant($amountToPay);
        $paiement->setDate(\DateTime::createFromInterface($dateTransaction));
        $paiement->setConsultation($consultation);

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) $amountToPay);
        $transaction->setDateTransaction(\DateTime::createFromInterface($dateTransaction));
        $transaction->setDescription(sprintf('Encaissement part patient | Facture assurance #%d', $facture->getId()));
        $transaction->setMotif('Encaissement part patient assurance');
        $transaction->setModeDePaiement($mode);
        $transaction->setConsultation($consultation);
        $transaction->setRolePaiement('patient_insurance');
        $transaction->setTauxPriseEnCharge($facture->getCoverageRate());
        $transaction->markValidated($dateTransaction);
        $transaction->setPaiement($paiement);

        $this->em->persist($paiement);
        $this->em->persist($transaction);
        $this->em->flush();

        $remainingAfter = max(0.0, $patientTotal - $this->resolvePatientPaidAmount($facture));

        return [
            'success' => true,
            'paiementId' => $paiement->getId(),
            'transactionId' => $transaction->getId(),
            'restePatient' => $remainingAfter,
        ];
    }

    private function resolveClaimAmount(FactureAssurance $facture): float
    {
        $montants = $facture->computeTotals();
        return max(0.0, (float) ($montants['montantAssureur'] ?? 0.0));
    }

    private function mapClaim(FactureAssurance $facture, bool $withLines = false): array
    {
        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient() ?? $facture->getPatient();
        $status = $facture->getInsuranceStatus();
        $claimAmount = $this->resolveClaimAmount($facture);
        $montants = $facture->computeTotals();
        $patientPaidAmount = $this->resolvePatientPaidAmount($facture);
        $patientRemaining = max(0.0, (float) ($montants['montantPatient'] ?? 0.0) - $patientPaidAmount);
        $lot = $facture->getLotFactureAssurance();

        $data = [
            'id' => $facture->getId(),
            'consultationId' => $consultation?->getId(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
            'patient' => $patient?->getFullName(),
            'telephone' => $patient?->getTelephone(),
            'assurance' => [
                'id' => $facture->getAssurance()?->getId(),
                'nom' => $facture->getAssurance()?->getNom(),
                'code' => $facture->getAssurance()?->getCode(),
                'logoPath' => $facture->getAssurance()?->getLogoPath(),
            ],
            'tauxCouverture' => $facture->getCoverageRate(),
            'montantTotal' => (float) ($montants['montantTotal'] ?? 0.0),
            'montantAssurance' => (float) ($montants['montantAssureur'] ?? $claimAmount),
            'montantPatient' => (float) ($montants['montantPatient'] ?? 0.0),
            'patientPaidAmount' => $patientPaidAmount,
            'restePatient' => $patientRemaining,
            'isRecouvre' => $facture->isRecouvre(),
            'insuranceStatus' => $status,
            'lotId' => $lot?->getId(),
            'lotStatut' => $lot?->getStatut(),
            'lotDescription' => $lot?->getDescription(),
            'availableActions' => [
                'canValidate' => $status === 'pending',
                'canReject' => in_array($status, ['pending', 'validated'], true) && !$facture->isRecouvre(),
                'canCollectPatient' => $patientRemaining > 0,
            ],
        ];

        if ($withLines) {
            $data['lignes'] = $this->buildClaimLines($facture);
            $data['assuranceSnapshot'] = $facture->getAssuranceSnapshot();
        }

        return $data;
    }

    private function resolveClaimPayments(FactureAssurance $facture): array
    {
        $consultation = $facture->getConsultation();
        if (!$consultation) {
            return [];
        }

        $transactions = $this->em->createQueryBuilder()
            ->select('t', 'p', 'm')
            ->from(Transaction::class, 't')
            ->leftJoin('t.paiement', 'p')
            ->leftJoin('t.modeDePaiement', 'm')
            ->where('t.consultation = :consultation')
            ->andWhere('t.rolePaiement = :role')
            ->andWhere('t.validationStatus = :status')
            ->setParameter('consultation', $consultation)
            ->setParameter('role', 'patient_insurance')
            ->setParameter('status', 'validated')
            ->orderBy('t.dateTransaction', 'DESC')
            ->getQuery()
            ->getResult();

        $payments = [];
        foreach ($transactions as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }
            $paiement = $transaction->getPaiement();
            $payments[] = [
                'transactionId' => $transaction->getId(),
                'paiementId' => $paiement?->getId(),
                'montant' => (float) $transaction->getMontant(),
                'date' => $transaction->getDateTransaction()?->format('Y-m-d H:i:s'),
                'mode' => $transaction->getModeDePaiement()?->getLibelle(),
                'description' => $transaction->getDescription(),
            ];
        }

        return $payments;
    }

    private function resolvePatientPaidAmount(FactureAssurance $facture): float
    {
        $consultation = $facture->getConsultation();
        if (!$consultation) {
            return 0.0;
        }

        return max(0.0, (float) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(t.montant), 0)')
            ->from(Transaction::class, 't')
            ->where('t.consultation = :consultation')
            ->andWhere('t.rolePaiement = :role')
            ->andWhere('t.validationStatus = :status')
            ->setParameter('consultation', $consultation)
            ->setParameter('role', 'patient_insurance')
            ->setParameter('status', 'validated')
            ->getQuery()
            ->getSingleScalarResult());
    }

    private function buildClaimLines(FactureAssurance $facture): array
    {
        $lines = [];

        foreach ($facture->buildLignes() as $line) {
            $quantite = max(1, (int) ($line['quantite'] ?? $line['qte'] ?? 1));
            $montant = (float) ($line['prix'] ?? $line['montant'] ?? 0);
            $designation = trim((string) ($line['type'] ?? $line['designation'] ?? 'Soin'));
            if ($designation === '') {
                $designation = 'Soin';
            }

            $description = trim((string) ($line['description'] ?? ''));
            $lineDto = new InsuranceClaimLineDto(
                'invoice_acte',
                $designation,
                $quantite,
                $montant,
                (float) ($line['total'] ?? ($quantite * $montant)),
                $description !== '' ? $description : null,
                true,
            );

            $lines[] = $lineDto->toArray();
        }

        return $lines;
    }
}

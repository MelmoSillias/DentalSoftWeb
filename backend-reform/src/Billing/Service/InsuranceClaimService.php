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
    ) {
    }

    public function listClaims(
        ?string $status = null,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
        ?string $patientQuery = null,
        ?string $assuranceCode = null,
        bool $closedOnly = true,
    ): array
    {
        $qb = $this->factureAssuranceRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('f.assurance', 'a')->addSelect('a')
            ->leftJoin('f.lotFactureAssurance', 'l')->addSelect('l')
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ($closedOnly) {
            $qb->andWhere('c.statut = 1');
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'ready') {
                $qb->andWhere('c.statut = 1')->andWhere('f.lotFactureAssurance IS NULL')->andWhere('f.isRecouvre = false');
            } elseif ($status === 'open') {
                $qb->andWhere('c.statut = 0');
            } else {
                $qb->andWhere('f.insuranceStatus = :status')
                    ->setParameter('status', $status);
            }
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

    public function getClaimDetail(int $factureId): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        $consultation = $facture->getConsultation();
        if ($consultation && $consultation->getStatut() !== 1) {
            return ['error' => 'Facture assurance non visible tant que la consultation n est pas cloturee', 'status' => 404];
        }

        $claim = $this->mapClaim($facture, true);
        $claim['paiements'] = $this->resolveClaimPayments($facture);

        return ['data' => $claim];
    }

    public function payPatientShare(int $factureId, int $modeId, ?float $amount = null, ?\DateTimeInterface $date = null): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        $consultation = $facture->getConsultation();
        if (!$consultation || $consultation->getStatut() !== 1) {
            return ['error' => 'La consultation doit etre cloturee avant encaissement', 'status' => 400];
        }

        $mode = $this->modeRepository->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
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
        $facture->addPaiement($paiement);

        $patientName = $consultation->getPatient()?->getFullName() ?? '';

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) $amountToPay);
        $transaction->setDateTransaction(\DateTime::createFromInterface($dateTransaction));
        $transaction->setDescription(sprintf('Encaissement patient | Facture assurance #%d | %s', $facture->getId(), $patientName));
        $transaction->setMotif('Encaissement patient assurance');
        $transaction->setModeDePaiement($mode);
        $transaction->setRolePaiement('patient_insurance');
        $transaction->markValidated($dateTransaction);
        $transaction->setPaiement($paiement);

        $this->em->persist($paiement);
        $this->em->persist($transaction);

        $this->em->flush();

        $remainingAfter = max(0.0, $patientTotal - ($alreadyPaid + $amountToPay));

        return [
            'success' => true,
            'paiementId' => $paiement->getId(),
            'transactionId' => $transaction->getId(),
            'restePatient' => $remainingAfter,
        ];
    }

    public function canModifyFactureAssurance(FactureAssurance $facture): bool
    {
        $lot = $facture->getLotFactureAssurance();
        if ($lot !== null) {
            $statut = $lot->getStatut();
            if ($statut === 'recouvre') {
                $statut = 'rembourse';
            }
            if (in_array($statut, ['envoye', 'confirme', 'partiellement_rembourse', 'rembourse'], true)) {
                return false;
            }
        }

        return $facture->computePatientPaidAmount() <= 0;
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
        $claimAmount = $this->resolveClaimAmount($facture);
        $montants = $facture->computeTotals();
        $patientPaidAmount = $this->resolvePatientPaidAmount($facture);
        $patientRemaining = max(0.0, (float) ($montants['montantPatient'] ?? 0.0) - $patientPaidAmount);
        $lot = $facture->getLotFactureAssurance();
        $status = $this->resolveDerivedStatus($facture);

        $data = [
            'id' => $facture->getId(),
            'consultationId' => $consultation?->getId(),
            'factureId' => $facture->getId(),
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
            'consultationAmount' => (float) ($montants['consultationAmount'] ?? 0.0),
            'patientPaidAmount' => $patientPaidAmount,
            'restePatient' => $patientRemaining,
            'isRecouvre' => $facture->isRecouvre(),
            'insuranceStatus' => $status,
            'lotId' => $lot?->getId(),
            'lotStatut' => $lot?->getStatut() === 'recouvre' ? 'rembourse' : $lot?->getStatut(),
            'lotDescription' => $lot?->getDescription(),
            'availableActions' => [
                'canCollectPatient' => $patientRemaining > 0,
                'canModify' => $this->canModifyFactureAssurance($facture),
                'canAssignLot' => $lot === null && ($consultation?->getStatut() === 1),
                'canChangeLot' => $lot !== null && $lot->getStatut() === 'ouvert',
            ],
        ];

        if ($withLines) {
            $data['lignes'] = $this->buildClaimDisplayLines($facture);
            $data['lignesEditables'] = $this->buildClaimEditableLines($facture);
            $data['assuranceSnapshot'] = $facture->getAssuranceSnapshot();
        }

        return $data;
    }

    private function resolveDerivedStatus(FactureAssurance $facture): string
    {
        $consultation = $facture->getConsultation();
        if ($consultation && $consultation->getStatut() !== 1) {
            return 'open';
        }
        if ($facture->isRecouvre()) {
            return 'rembourse';
        }
        if ($facture->getLotFactureAssurance()) {
            return 'in_lot';
        }

        return 'ready';
    }

    private function resolveClaimPayments(FactureAssurance $facture): array
    {
        $payments = [];
        foreach ($facture->getPaiements() as $paiement) {
            $transaction = $paiement->getTransaction();
            $status = $transaction?->getValidationStatus();
            if ($status !== null && $status !== 'validated') {
                continue;
            }

            $payments[] = [
                'transactionId' => $transaction?->getId(),
                'paiementId' => $paiement->getId(),
                'montant' => $paiement->getMontant(),
                'date' => $paiement->getDate()?->format('Y-m-d H:i:s'),
                'mode' => $paiement->getMode()?->getLibelle(),
                'description' => $transaction?->getDescription(),
            ];
        }

        usort($payments, static fn (array $a, array $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $payments;
    }

    private function resolvePatientPaidAmount(FactureAssurance $facture): float
    {
        return $facture->computePatientPaidAmount();
    }

    private function buildClaimDisplayLines(FactureAssurance $facture): array
    {
        $lines = [];
        foreach ($facture->buildDisplayLignes() as $line) {
            $quantite = max(1, (int) ($line['quantite'] ?? 1));
            $montant = (float) ($line['prix'] ?? 0);
            $designation = trim((string) ($line['designation'] ?? 'Soin'));
            if ($designation === '') {
                $designation = 'Soin';
            }
            $description = trim((string) ($line['description'] ?? ''));
            $isVirtual = !empty($line['virtual']);

            $lineDto = new InsuranceClaimLineDto(
                $isVirtual ? 'consultation' : 'invoice_acte',
                $designation,
                $quantite,
                $montant,
                (float) ($line['total'] ?? ($quantite * $montant)),
                $description !== '' ? $description : null,
                !$isVirtual,
            );

            $mapped = $lineDto->toArray();
            $mapped['virtual'] = $isVirtual;
            $lines[] = $mapped;
        }

        return $lines;
    }

    private function buildClaimEditableLines(FactureAssurance $facture): array
    {
        $lines = [];
        foreach ($facture->buildActeLignes() as $line) {
            $quantite = max(1, (int) ($line['quantite'] ?? 1));
            $montant = (float) ($line['prix'] ?? 0);
            $designation = trim((string) ($line['designation'] ?? 'Soin'));
            $description = trim((string) ($line['description'] ?? ''));

            $lineDto = new InsuranceClaimLineDto(
                'invoice_acte',
                $designation !== '' ? $designation : 'Soin',
                $quantite,
                $montant,
                (float) ($line['total'] ?? ($quantite * $montant)),
                $description !== '' ? $description : null,
                true,
            );

            $mapped = $lineDto->toArray();
            $mapped['virtual'] = false;
            $lines[] = $mapped;
        }

        return $lines;
    }
}

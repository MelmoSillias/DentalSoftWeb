<?php

namespace App\Billing\Service;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\LotFactureAssurance;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\AssuranceRepository;
use App\Billing\Repository\FactureAssuranceRepository;
use App\Billing\Repository\LotFactureAssuranceRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use Doctrine\ORM\EntityManagerInterface;

class LotFactureAssuranceService
{
    public const STATUT_OUVERT = 'ouvert';
    public const STATUT_ENVOYE = 'envoye';
    public const STATUT_CONFIRME = 'confirme';
    public const STATUT_PARTIELLEMENT_REMBOURSE = 'partiellement_rembourse';
    public const STATUT_REMBOURSE = 'rembourse';

    /** Legacy status mapped to rembourse */
    private const LEGACY_RECOUVRE = 'recouvre';

    public function __construct(
        private LotFactureAssuranceRepository $lotRepository,
        private AssuranceRepository $assuranceRepository,
        private FactureAssuranceRepository $factureAssuranceRepository,
        private ModeDePaiementRepository $modeRepository,
        private IntegratedInsuranceCatalog $catalog,
        private EntityManagerInterface $em,
    ) {
    }

    public function getDashboard(): array
    {
        $catalogCodes = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['code'] ?? ''),
            $this->catalog->getCatalog(),
        ), static fn (string $code): bool => $code !== ''));

        $this->catalog->syncCatalog($this->assuranceRepository, $this->em);

        $assurances = array_values(array_filter(
            $this->assuranceRepository->findBy(['actif' => true], ['nom' => 'ASC']),
            static fn (Assurance $assurance): bool => in_array((string) $assurance->getCode(), $catalogCodes, true),
        ));

        return array_map(fn (Assurance $assurance) => $this->mapAssuranceDashboard($assurance), $assurances);
    }

    public function listLots(string $assuranceCode, ?string $statut = null): array
    {
        $assurance = $this->assuranceRepository->findOneByCode($assuranceCode);
        if (!$assurance) {
            return ['error' => 'Assurance introuvable', 'status' => 404];
        }

        $qb = $this->lotRepository->createQueryBuilder('l')
            ->andWhere('l.assurance = :assurance')
            ->setParameter('assurance', $assurance)
            ->orderBy('l.dateCreation', 'DESC');

        if ($statut !== null && $statut !== '' && $statut !== 'all') {
            if ($statut === self::STATUT_REMBOURSE) {
                $qb->andWhere('l.statut IN (:statuts)')
                    ->setParameter('statuts', [self::STATUT_REMBOURSE, self::STATUT_PARTIELLEMENT_REMBOURSE, self::LEGACY_RECOUVRE]);
            } else {
                $qb->andWhere('l.statut = :statut')->setParameter('statut', $statut);
            }
        }

        $lots = $qb->getQuery()->getResult();

        return [
            'assurance' => $this->mapAssuranceSummary($assurance),
            'openLots' => array_map(
                fn (LotFactureAssurance $lot) => $this->mapLotSummary($lot),
                $this->lotRepository->findOpenLotsForAssurance($assurance)
            ),
            'unassignedClaims' => $this->listUnassignedClaims($assurance),
            'data' => array_map(fn (LotFactureAssurance $lot) => $this->mapLotSummary($lot), $lots),
        ];
    }

    public function getLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        return ['data' => $this->mapLotDetail($lot)];
    }

    public function openLot(
        string $assuranceCode,
        ?string $description = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
    ): array {
        $assurance = $this->assuranceRepository->findOneByCode($assuranceCode);
        if (!$assurance) {
            return ['error' => 'Assurance introuvable', 'status' => 404];
        }

        $now = new \DateTime();
        $lot = new LotFactureAssurance();
        $lot->setAssurance($assurance);
        $lot->setDescription($description ?: sprintf('Lot %s', $now->format('d/m/Y')));
        $lot->setDateDebut($dateDebut ?? (clone $now)->setTime(0, 0, 0));
        $lot->setDateFin($dateFin ?? (clone $now)->setTime(23, 59, 59));
        $lot->setStatut(self::STATUT_OUVERT);
        $lot->setDateCreation($now);

        $this->em->persist($lot);
        $this->em->flush();

        return ['success' => true, 'lotId' => $lot->getId(), 'data' => $this->mapLotDetail($lot)];
    }

    public function updateLot(
        int $lotId,
        ?string $description = null,
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
    ): array {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        $statut = $this->normalizeStatut($lot->getStatut());
        if ($statut === self::STATUT_REMBOURSE) {
            return ['error' => 'Impossible de modifier un lot entierement rembourse', 'status' => 400];
        }

        if ($description !== null) {
            $trimmed = trim($description);
            if ($trimmed === '') {
                return ['error' => 'Le nom du lot est obligatoire', 'status' => 400];
            }
            $lot->setDescription($trimmed);
        }

        if ($dateDebut !== null) {
            $lot->setDateDebut($dateDebut);
        }
        if ($dateFin !== null) {
            $lot->setDateFin($dateFin);
        }

        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function addClaimToLot(int $lotId, int $factureId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_OUVERT) {
            return ['error' => 'Seul un lot ouvert accepte de nouvelles factures', 'status' => 400];
        }

        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture assurance introuvable', 'status' => 404];
        }

        $consultation = $facture->getConsultation();
        if (!$consultation || $consultation->getStatut() !== 1) {
            return ['error' => 'La consultation doit etre cloturee avant affectation au lot', 'status' => 400];
        }

        if ($facture->isRecouvre()) {
            return ['error' => 'Facture deja remboursee', 'status' => 400];
        }

        $lotAssurance = $lot->getAssurance();
        if ($facture->getAssurance()?->getId() !== $lotAssurance?->getId()) {
            return ['error' => 'La facture appartient a une autre assurance', 'status' => 400];
        }

        if ($facture->getLotFactureAssurance() !== null && $facture->getLotFactureAssurance()->getId() !== $lotId) {
            return ['error' => 'La facture est deja dans un autre lot', 'status' => 400];
        }

        $lot->addFactureAssurance($facture);
        if ($facture->getInsuranceStatus() === 'pending' || $facture->getInsuranceStatus() === 'open') {
            $facture->setInsuranceStatus('ready');
        }
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function removeClaimFromLot(int $lotId, int $factureId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_OUVERT) {
            return ['error' => 'Impossible de retirer une facture d un lot non ouvert', 'status' => 400];
        }

        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture || $facture->getLotFactureAssurance()?->getId() !== $lotId) {
            return ['error' => 'Facture introuvable dans ce lot', 'status' => 404];
        }

        $lot->removeFactureAssurance($facture);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function moveClaimToLot(int $factureId, int $targetLotId): array
    {
        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture assurance introuvable', 'status' => 404];
        }

        $currentLot = $facture->getLotFactureAssurance();
        if ($currentLot !== null && $this->normalizeStatut($currentLot->getStatut()) !== self::STATUT_OUVERT) {
            return ['error' => 'Impossible de changer de lot : le lot actuel n est pas ouvert', 'status' => 400];
        }

        if ($currentLot !== null) {
            $currentLot->removeFactureAssurance($facture);
        }

        return $this->addClaimToLot($targetLotId, $factureId);
    }

    public function sendLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_OUVERT) {
            return ['error' => 'Seul un lot ouvert peut etre envoye', 'status' => 400];
        }

        if ($lot->getFacturesAssurance()->isEmpty()) {
            return ['error' => 'Le lot ne contient aucune facture', 'status' => 400];
        }

        $lot->setStatut(self::STATUT_ENVOYE);
        $lot->setDateEnvoi(new \DateTime());
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function reopenLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_ENVOYE) {
            return ['error' => 'Seul un lot envoye peut revenir a ouvert', 'status' => 400];
        }

        $lot->setStatut(self::STATUT_OUVERT);
        $lot->setDateEnvoi(null);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function confirmLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_ENVOYE) {
            return ['error' => 'Seul un lot envoye peut etre confirme', 'status' => 400];
        }

        $lot->setStatut(self::STATUT_CONFIRME);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function unconfirmLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_CONFIRME) {
            return ['error' => 'Seul un lot confirme sans remboursement peut revenir a envoye', 'status' => 400];
        }

        if ($this->sumValidatedRefunds($lot) > 0) {
            return ['error' => 'Impossible de revenir en arriere : des remboursements existent', 'status' => 400];
        }

        $lot->setStatut(self::STATUT_ENVOYE);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function refundLot(int $lotId, int $modeId, ?float $amount = null, ?\DateTimeInterface $date = null): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        $statut = $this->normalizeStatut($lot->getStatut());
        if (!in_array($statut, [self::STATUT_CONFIRME, self::STATUT_PARTIELLEMENT_REMBOURSE], true)) {
            return ['error' => 'Seul un lot confirme ou partiellement rembourse peut etre rembourse', 'status' => 400];
        }

        $mode = $this->modeRepository->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $montantAssureur = $lot->computeMontantAssureur();
        $dejaRembourse = $this->sumValidatedRefunds($lot);
        $reste = max(0.0, $montantAssureur - $dejaRembourse);

        if ($reste <= 0) {
            return ['error' => 'Ce lot est deja entierement rembourse', 'status' => 400];
        }

        $montant = $amount === null ? $reste : (float) $amount;
        if ($montant <= 0) {
            return ['error' => 'Montant de remboursement invalide', 'status' => 400];
        }
        if ($montant > $reste) {
            return ['error' => 'Le montant depasse le reste a rembourser', 'status' => 400];
        }

        $assurance = $lot->getAssurance();
        $dateTransaction = $date ?? new \DateTimeImmutable();
        $assuranceNom = $assurance?->getNom() ?? $assurance?->getCode() ?? 'Assurance';
        $lotNom = $lot->getDescription() ?: sprintf('Lot #%d', $lot->getId());

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) round($montant));
        $transaction->setDateTransaction(\DateTime::createFromInterface($dateTransaction));
        $transaction->setMotif('Remboursement assurance');
        $transaction->setDescription(sprintf(
            'Remboursement d\'assurance | %s | %s | %s FCFA',
            $assuranceNom,
            $lotNom,
            number_format($montant, 0, ',', ' ')
        ));
        $transaction->setModeDePaiement($mode);
        $transaction->setLotFactureAssurance($lot);
        $transaction->setRolePaiement('insurance_lot');
        $transaction->markValidated($dateTransaction);

        $this->em->persist($transaction);

        $nouveauReste = max(0.0, $reste - $montant);
        $this->applyRefundStatus($lot, $nouveauReste, $dateTransaction);

        $this->em->flush();

        return [
            'success' => true,
            'transactionId' => $transaction->getId(),
            'resteARembourser' => $nouveauReste,
            'data' => $this->mapLotDetail($lot),
        ];
    }

    /** @deprecated Use refundLot */
    public function recoverLot(int $lotId, int $modeId, ?\DateTimeInterface $date = null): array
    {
        return $this->refundLot($lotId, $modeId, null, $date);
    }

    public function cancelRefund(int $lotId, int $transactionId, ?string $comment = null): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        $statut = $this->normalizeStatut($lot->getStatut());
        if ($statut === self::STATUT_REMBOURSE) {
            return ['error' => 'Impossible d annuler un remboursement sur un lot entierement rembourse', 'status' => 400];
        }

        if ($statut !== self::STATUT_PARTIELLEMENT_REMBOURSE) {
            return ['error' => 'Aucun remboursement partiel a annuler', 'status' => 400];
        }

        $transaction = null;
        foreach ($lot->getTransactions() as $tx) {
            if ($tx instanceof Transaction && $tx->getId() === $transactionId) {
                $transaction = $tx;
                break;
            }
        }

        if (!$transaction || $transaction->getRolePaiement() !== 'insurance_lot' || $transaction->getValidationStatus() !== 'validated') {
            return ['error' => 'Transaction de remboursement introuvable', 'status' => 404];
        }

        $transaction->markRejected($comment ?? 'Annulation remboursement lot assurance');

        $reste = max(0.0, $lot->computeMontantAssureur() - $this->sumValidatedRefunds($lot));
        $this->applyRefundStatus($lot, $reste, null);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    /** @deprecated Use cancelRefund */
    public function cancelLotRecovery(int $lotId, ?string $comment = null): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        $statut = $this->normalizeStatut($lot->getStatut());
        if ($statut === self::STATUT_REMBOURSE) {
            return ['error' => 'Impossible d annuler un lot entierement rembourse', 'status' => 400];
        }

        $transactions = $this->listValidatedRefundTransactions($lot);
        if ($transactions === []) {
            return ['error' => 'Aucune transaction de remboursement a annuler', 'status' => 404];
        }

        $last = $transactions[array_key_last($transactions)];

        return $this->cancelRefund($lotId, (int) $last->getId(), $comment);
    }

    public function rollbackLotRecoveryFromTransaction(Transaction $transaction): void
    {
        $lot = $transaction->getLotFactureAssurance();
        if (!$lot || $transaction->getRolePaiement() !== 'insurance_lot') {
            return;
        }

        $statut = $this->normalizeStatut($lot->getStatut());
        if ($statut === self::STATUT_REMBOURSE) {
            return;
        }

        $reste = max(0.0, $lot->computeMontantAssureur() - $this->sumValidatedRefunds($lot));
        $this->applyRefundStatus($lot, $reste, null);
    }

    private function applyRefundStatus(LotFactureAssurance $lot, float $resteARembourser, ?\DateTimeInterface $date): void
    {
        if ($resteARembourser <= 0) {
            $lot->setStatut(self::STATUT_REMBOURSE);
            $lot->setDateRecouvrement($date ? \DateTime::createFromInterface($date) : new \DateTime());
            foreach ($lot->getFacturesAssurance() as $facture) {
                if ($facture instanceof FactureAssurance) {
                    $facture->setIsRecouvre(true);
                    $facture->setInsuranceStatus('rembourse');
                }
            }

            return;
        }

        $deja = $this->sumValidatedRefunds($lot);
        if ($deja > 0) {
            $lot->setStatut(self::STATUT_PARTIELLEMENT_REMBOURSE);
            $lot->setDateRecouvrement(null);
            foreach ($lot->getFacturesAssurance() as $facture) {
                if ($facture instanceof FactureAssurance) {
                    $facture->setIsRecouvre(false);
                    $facture->setInsuranceStatus('ready');
                }
            }

            return;
        }

        $lot->setStatut(self::STATUT_CONFIRME);
        $lot->setDateRecouvrement(null);
        foreach ($lot->getFacturesAssurance() as $facture) {
            if ($facture instanceof FactureAssurance) {
                $facture->setIsRecouvre(false);
                $facture->setInsuranceStatus('ready');
            }
        }
    }

    private function sumValidatedRefunds(LotFactureAssurance $lot): float
    {
        $total = 0.0;
        foreach ($this->listValidatedRefundTransactions($lot) as $transaction) {
            $total += (float) $transaction->getMontant();
        }

        return $total;
    }

    /** @return list<Transaction> */
    private function listValidatedRefundTransactions(LotFactureAssurance $lot): array
    {
        $result = [];
        foreach ($lot->getTransactions() as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }
            if ($transaction->getRolePaiement() === 'insurance_lot' && $transaction->getValidationStatus() === 'validated') {
                $result[] = $transaction;
            }
        }

        usort($result, static function (Transaction $a, Transaction $b): int {
            $da = $a->getDateTransaction()?->getTimestamp() ?? 0;
            $db = $b->getDateTransaction()?->getTimestamp() ?? 0;

            return $da <=> $db;
        });

        return $result;
    }

    private function listUnassignedClaims(Assurance $assurance): array
    {
        $result = $this->factureAssuranceRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->andWhere('f.assurance = :assurance')
            ->andWhere('f.lotFactureAssurance IS NULL')
            ->andWhere('c.statut = 1')
            ->andWhere('f.isRecouvre = false')
            ->setParameter('assurance', $assurance)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (FactureAssurance $facture) => $this->mapUnassignedClaimSummary($facture), $result);
    }

    private function mapUnassignedClaimSummary(FactureAssurance $facture): array
    {
        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient() ?? $facture->getPatient();
        $montants = $facture->computeTotals();
        $patientPaid = $this->resolvePatientPaidAmount($facture);
        $restePatient = max(0.0, (float) ($montants['montantPatient'] ?? 0.0) - $patientPaid);

        return [
            'id' => $facture->getId(),
            'consultationId' => $consultation?->getId(),
            'factureId' => $consultation?->getFacture()?->getId(),
            'patient' => $patient?->getFullName(),
            'telephone' => $patient?->getTelephone(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
            'insuranceStatus' => $this->resolveClaimStatus($facture),
            'montantAssurance' => (float) ($montants['montantAssureur'] ?? 0.0),
            'montantTotal' => (float) ($montants['montantTotal'] ?? 0.0),
            'montantPatient' => (float) ($montants['montantPatient'] ?? 0.0),
            'patientPaidAmount' => $patientPaid,
            'restePatient' => $restePatient,
            'tauxCouverture' => $facture->getCoverageRate(),
            'canPay' => $restePatient > 0,
            'canModify' => $patientPaid <= 0,
        ];
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

    private function mapAssuranceSummary(Assurance $assurance): array
    {
        return [
            'id' => $assurance->getId(),
            'nom' => $assurance->getNom(),
            'code' => $assurance->getCode(),
            'logoPath' => $assurance->getLogoPath(),
        ];
    }

    private function mapAssuranceDashboard(Assurance $assurance): array
    {
        $counts = $this->computeLotStatusCounts($assurance);
        $sansLot = (int) $this->em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FactureAssurance::class, 'f')
            ->leftJoin('f.consultation', 'c')
            ->andWhere('f.assurance = :assurance')
            ->andWhere('f.lotFactureAssurance IS NULL')
            ->andWhere('c.statut = 1')
            ->setParameter('assurance', $assurance)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'id' => $assurance->getId(),
            'nom' => $assurance->getNom(),
            'code' => $assurance->getCode(),
            'logoPath' => $assurance->getLogoPath(),
            'actif' => $assurance->isActif(),
            'counts' => [
                'sansLot' => $sansLot,
                'ouverts' => $counts[self::STATUT_OUVERT],
                'envoyes' => $counts[self::STATUT_ENVOYE],
                'confirmes' => $counts[self::STATUT_CONFIRME],
                'rembourses' => $counts[self::STATUT_REMBOURSE] + $counts[self::STATUT_PARTIELLEMENT_REMBOURSE],
            ],
        ];
    }

    private function computeLotStatusCounts(Assurance $assurance): array
    {
        $counts = [
            self::STATUT_OUVERT => 0,
            self::STATUT_ENVOYE => 0,
            self::STATUT_CONFIRME => 0,
            self::STATUT_PARTIELLEMENT_REMBOURSE => 0,
            self::STATUT_REMBOURSE => 0,
        ];

        $lots = $this->lotRepository->createQueryBuilder('l')
            ->andWhere('l.assurance = :assurance')
            ->setParameter('assurance', $assurance)
            ->getQuery()
            ->getResult();

        foreach ($lots as $lot) {
            if (!$lot instanceof LotFactureAssurance) {
                continue;
            }
            $statut = $this->normalizeStatut($lot->getStatut());
            if (!isset($counts[$statut])) {
                continue;
            }
            ++$counts[$statut];
        }

        return $counts;
    }

    private function mapLotSummary(LotFactureAssurance $lot): array
    {
        $factures = $lot->getFacturesAssurance();
        $montantAssureur = $lot->computeMontantAssureur();
        $rembourse = $this->sumValidatedRefunds($lot);
        $reste = max(0.0, $montantAssureur - $rembourse);

        return [
            'id' => $lot->getId(),
            'description' => $lot->getDescription(),
            'statut' => $this->normalizeStatut($lot->getStatut()),
            'dateDebut' => $lot->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $lot->getDateFin()?->format('Y-m-d'),
            'dateCreation' => $lot->getDateCreation()?->format('Y-m-d H:i:s'),
            'dateEnvoi' => $lot->getDateEnvoi()?->format('Y-m-d H:i:s'),
            'dateRecouvrement' => $lot->getDateRecouvrement()?->format('Y-m-d H:i:s'),
            'nbFactures' => $factures->count(),
            'montantTotal' => $montantAssureur,
            'montantRembourse' => $rembourse,
            'resteARembourser' => $reste,
            'assurance' => $this->mapAssuranceSummary($lot->getAssurance()),
            'availableActions' => $this->resolveLotActions($lot),
        ];
    }

    private function mapLotDetail(LotFactureAssurance $lot): array
    {
        $summary = $this->mapLotSummary($lot);

        $factures = [];
        foreach ($lot->getFacturesAssurance() as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $totals = $facture->computeTotals();
            $patient = $facture->getConsultation()?->getPatient();
            $patientPaid = $this->resolvePatientPaidAmount($facture);
            $restePatient = max(0.0, (float) ($totals['montantPatient'] ?? 0.0) - $patientPaid);
            $factures[] = [
                'id' => $facture->getId(),
                'consultationId' => $facture->getConsultation()?->getId(),
                'factureId' => $facture->getConsultation()?->getFacture()?->getId(),
                'patient' => $patient?->getFullName(),
                'telephone' => $patient?->getTelephone(),
                'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
                'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
                'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
                'montantPatient' => (float) ($totals['montantPatient'] ?? 0.0),
                'patientPaidAmount' => $patientPaid,
                'restePatient' => $restePatient,
                'insuranceStatus' => $this->resolveClaimStatus($facture),
                'tauxCouverture' => $facture->getCoverageRate(),
                'canPay' => $restePatient > 0,
                'canModify' => $this->canModifyClaim($facture, $lot),
            ];
        }

        $remboursements = [];
        foreach ($this->listValidatedRefundTransactions($lot) as $transaction) {
            $remboursements[] = [
                'id' => $transaction->getId(),
                'montant' => (float) $transaction->getMontant(),
                'description' => $transaction->getDescription(),
                'motif' => $transaction->getMotif(),
                'dateTransaction' => $transaction->getDateTransaction()?->format('Y-m-d H:i:s'),
                'validationStatus' => $transaction->getValidationStatus(),
                'modeDePaiement' => [
                    'id' => $transaction->getModeDePaiement()?->getId(),
                    'libelle' => $transaction->getModeDePaiement()?->getLibelle(),
                ],
                'canCancel' => $this->normalizeStatut($lot->getStatut()) === self::STATUT_PARTIELLEMENT_REMBOURSE,
            ];
        }

        $summary['factures'] = $factures;
        $summary['remboursements'] = $remboursements;

        return $summary;
    }

    private function canModifyClaim(FactureAssurance $facture, LotFactureAssurance $lot): bool
    {
        if ($this->normalizeStatut($lot->getStatut()) !== self::STATUT_OUVERT) {
            return false;
        }

        return $this->resolvePatientPaidAmount($facture) <= 0;
    }

    private function resolveClaimStatus(FactureAssurance $facture): string
    {
        $consultation = $facture->getConsultation();
        if ($consultation && $consultation->getStatut() !== 1) {
            return 'open';
        }

        if ($facture->isRecouvre()) {
            return 'rembourse';
        }

        $lot = $facture->getLotFactureAssurance();
        if ($lot) {
            return 'in_lot';
        }

        return 'ready';
    }

    private function resolveLotActions(LotFactureAssurance $lot): array
    {
        $statut = $this->normalizeStatut($lot->getStatut());
        $hasFactures = !$lot->getFacturesAssurance()->isEmpty();
        $hasRefunds = $this->sumValidatedRefunds($lot) > 0;

        return [
            'canEdit' => $statut !== self::STATUT_REMBOURSE,
            'canSend' => $statut === self::STATUT_OUVERT && $hasFactures,
            'canReopen' => $statut === self::STATUT_ENVOYE,
            'canConfirm' => $statut === self::STATUT_ENVOYE,
            'canUnconfirm' => $statut === self::STATUT_CONFIRME && !$hasRefunds,
            'canRefund' => in_array($statut, [self::STATUT_CONFIRME, self::STATUT_PARTIELLEMENT_REMBOURSE], true),
            'canCancelRefund' => $statut === self::STATUT_PARTIELLEMENT_REMBOURSE,
            'canAddClaims' => $statut === self::STATUT_OUVERT,
            'canRemoveClaims' => $statut === self::STATUT_OUVERT,
        ];
    }

    private function normalizeStatut(string $statut): string
    {
        if ($statut === self::LEGACY_RECOUVRE) {
            return self::STATUT_REMBOURSE;
        }

        return $statut;
    }
}

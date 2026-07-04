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
use App\Billing\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class LotFactureAssuranceService
{
    public function __construct(
        private LotFactureAssuranceRepository $lotRepository,
        private AssuranceRepository $assuranceRepository,
        private FactureAssuranceRepository $factureAssuranceRepository,
        private ModeDePaiementRepository $modeRepository,
        private TransactionRepository $transactionRepository,
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
            $qb->andWhere('l.statut = :statut')->setParameter('statut', $statut);
        }

        $lots = $qb->getQuery()->getResult();

        $openLot = $this->lotRepository->findOpenLotForAssurance($assurance);

        return [
            'assurance' => $this->mapAssuranceSummary($assurance),
            'openLot' => $openLot ? $this->mapLotSummary($openLot) : null,
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

        $existingOpen = $this->lotRepository->findOpenLotForAssurance($assurance);
        if ($existingOpen) {
            return ['error' => 'Un lot ouvert existe deja pour cette assurance', 'status' => 400, 'lotId' => $existingOpen->getId()];
        }

        $now = new \DateTime();
        $lot = new LotFactureAssurance();
        $lot->setAssurance($assurance);
        $lot->setDescription($description ?: sprintf('Lot %s', $now->format('d/m/Y')));
        $lot->setDateDebut($dateDebut ?? (clone $now)->setTime(0, 0, 0));
        $lot->setDateFin($dateFin ?? (clone $now)->setTime(23, 59, 59));
        $lot->setStatut('ouvert');
        $lot->setDateCreation($now);

        $this->em->persist($lot);
        $this->em->flush();

        return ['success' => true, 'lotId' => $lot->getId(), 'data' => $this->mapLotDetail($lot)];
    }

    public function addClaimToLot(int $lotId, int $factureId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($lot->getStatut() !== 'ouvert') {
            return ['error' => 'Seul un lot ouvert accepte de nouvelles factures', 'status' => 400];
        }

        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture assurance introuvable', 'status' => 404];
        }

        if ($facture->getInsuranceStatus() !== 'validated') {
            return ['error' => 'La facture doit etre validee avant ajout au lot', 'status' => 400];
        }

        if ($facture->isRecouvre()) {
            return ['error' => 'Facture deja recouvree', 'status' => 400];
        }

        $lotAssurance = $lot->getAssurance();
        if ($facture->getAssurance()?->getId() !== $lotAssurance?->getId()) {
            return ['error' => 'La facture appartient a une autre assurance', 'status' => 400];
        }

        if ($facture->getLotFactureAssurance() !== null && $facture->getLotFactureAssurance()->getId() !== $lotId) {
            return ['error' => 'La facture est deja dans un autre lot', 'status' => 400];
        }

        $lot->addFactureAssurance($facture);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function removeClaimFromLot(int $lotId, int $factureId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($lot->getStatut() !== 'ouvert') {
            return ['error' => 'Impossible de retirer une facture d un lot envoye ou recouvre', 'status' => 400];
        }

        $facture = $this->factureAssuranceRepository->find($factureId);
        if (!$facture || $facture->getLotFactureAssurance()?->getId() !== $lotId) {
            return ['error' => 'Facture introuvable dans ce lot', 'status' => 404];
        }

        $lot->removeFactureAssurance($facture);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function sendLot(int $lotId): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($lot->getStatut() !== 'ouvert') {
            return ['error' => 'Seul un lot ouvert peut etre envoye', 'status' => 400];
        }

        if ($lot->getFacturesAssurance()->isEmpty()) {
            return ['error' => 'Le lot ne contient aucune facture', 'status' => 400];
        }

        $lot->setStatut('envoye');
        $lot->setDateEnvoi(new \DateTime());
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function recoverLot(int $lotId, int $modeId, ?\DateTimeInterface $date = null): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($lot->getStatut() !== 'envoye') {
            return ['error' => 'Seul un lot envoye peut etre encaisse', 'status' => 400];
        }

        $existingTx = $this->findValidatedLotTransaction($lot);
        if ($existingTx) {
            return ['error' => 'Ce lot a deja ete encaisse', 'status' => 400];
        }

        $mode = $this->modeRepository->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $montant = $lot->computeMontantAssureur();
        if ($montant <= 0) {
            return ['error' => 'Montant lot invalide', 'status' => 400];
        }

        $assurance = $lot->getAssurance();
        $nbFactures = $lot->getFacturesAssurance()->count();
        $dateTransaction = $date ?? new \DateTimeImmutable();

        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) round($montant));
        $transaction->setDateTransaction(\DateTime::createFromInterface($dateTransaction));
        $transaction->setMotif('Recouvrement lot assurance');
        $transaction->setDescription(sprintf(
            'Recouvrement lot #%d | %s | %d factures | %s FCFA',
            $lot->getId(),
            $assurance?->getCode() ?? 'Assurance',
            $nbFactures,
            number_format($montant, 0, ',', ' ')
        ));
        $transaction->setModeDePaiement($mode);
        $transaction->setLotFactureAssurance($lot);
        $transaction->setRolePaiement('insurance_lot');
        $transaction->markValidated();

        foreach ($lot->getFacturesAssurance() as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $facture->setIsRecouvre(true);
            $facture->setInsuranceStatus('recouvre');
        }

        $lot->setStatut('recouvre');
        $lot->setDateRecouvrement(\DateTime::createFromInterface($dateTransaction));

        $this->em->persist($transaction);
        $this->em->flush();

        return [
            'success' => true,
            'transactionId' => $transaction->getId(),
            'data' => $this->mapLotDetail($lot),
        ];
    }

    public function cancelLotRecovery(int $lotId, ?string $comment = null): array
    {
        $lot = $this->lotRepository->find($lotId);
        if (!$lot) {
            return ['error' => 'Lot introuvable', 'status' => 404];
        }

        if ($lot->getStatut() !== 'recouvre') {
            return ['error' => 'Seul un lot recouvre peut etre annule', 'status' => 400];
        }

        $transaction = $this->findValidatedLotTransaction($lot);
        if (!$transaction) {
            return ['error' => 'Transaction de recouvrement introuvable', 'status' => 404];
        }

        $transaction->markRejected($comment ?? 'Annulation encaissement lot assurance');

        $this->rollbackLotRecoveryState($lot);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapLotDetail($lot)];
    }

    public function rollbackLotRecoveryFromTransaction(Transaction $transaction): void
    {
        $lot = $transaction->getLotFactureAssurance();
        if (!$lot || $transaction->getRolePaiement() !== 'insurance_lot') {
            return;
        }

        if ($lot->getStatut() !== 'recouvre') {
            return;
        }

        $this->rollbackLotRecoveryState($lot);
    }

    public function autoAssignClaimToOpenLot(FactureAssurance $facture): ?LotFactureAssurance
    {
        $assurance = $facture->getAssurance();
        if (!$assurance) {
            return null;
        }

        if ($facture->getLotFactureAssurance() !== null) {
            return $facture->getLotFactureAssurance();
        }

        $openLot = $this->lotRepository->findOpenLotForAssurance($assurance);
        if (!$openLot) {
            return null;
        }

        $openLot->addFactureAssurance($facture);

        return $openLot;
    }

    private function rollbackLotRecoveryState(LotFactureAssurance $lot): void
    {
        $lot->setStatut('envoye');
        $lot->setDateRecouvrement(null);

        foreach ($lot->getFacturesAssurance() as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $facture->setIsRecouvre(false);
            $facture->setInsuranceStatus('validated');
        }
    }

    private function findValidatedLotTransaction(LotFactureAssurance $lot): ?Transaction
    {
        foreach ($lot->getTransactions() as $transaction) {
            if (!$transaction instanceof Transaction) {
                continue;
            }
            if ($transaction->getRolePaiement() === 'insurance_lot' && $transaction->getValidationStatus() === 'validated') {
                return $transaction;
            }
        }

        return null;
    }

    private function listUnassignedClaims(Assurance $assurance): array
    {
        $result = $this->factureAssuranceRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->andWhere('f.assurance = :assurance')
            ->andWhere('f.lotFactureAssurance IS NULL')
            ->andWhere('f.insuranceStatus = :status')
            ->andWhere('f.isRecouvre = false')
            ->setParameter('assurance', $assurance)
            ->setParameter('status', 'validated')
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

        return [
            'id' => $facture->getId(),
            'patient' => $patient?->getFullName(),
            'telephone' => $patient?->getTelephone(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
            'insuranceStatus' => $facture->getInsuranceStatus(),
            'montantAssurance' => (float) ($montants['montantAssureur'] ?? 0.0),
            'montantTotal' => (float) ($montants['montantTotal'] ?? 0.0),
            'tauxCouverture' => $facture->getCoverageRate(),
        ];
    }

    private function computeAssuranceReliquat(Assurance $assurance): float
    {
        $result = $this->em->createQueryBuilder()
            ->select('f')
            ->from(FactureAssurance::class, 'f')
            ->andWhere('f.assurance = :assurance')
            ->andWhere('f.isRecouvre = false')
            ->andWhere('f.insuranceStatus IN (:statuses)')
            ->setParameter('assurance', $assurance)
            ->setParameter('statuses', ['pending', 'validated'])
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($result as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $totals = $facture->computeTotals();
            $total += (float) ($totals['montantAssureur'] ?? 0.0);
        }

        return $total;
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
        $openLot = $this->lotRepository->findLatestOpenLotForAssurance($assurance);
        $reliquat = $this->computeAssuranceReliquat($assurance);

        $pendingCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FactureAssurance::class, 'f')
            ->andWhere('f.assurance = :assurance')
            ->andWhere('f.insuranceStatus = :status')
            ->setParameter('assurance', $assurance)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'id' => $assurance->getId(),
            'nom' => $assurance->getNom(),
            'code' => $assurance->getCode(),
            'logoPath' => $assurance->getLogoPath(),
            'actif' => $assurance->isActif(),
            'reliquatTotal' => $reliquat,
            'pendingClaimsCount' => $pendingCount,
            'dernierLotOuvert' => $openLot ? $this->mapLotSummary($openLot) : null,
        ];
    }

    private function mapLotSummary(LotFactureAssurance $lot): array
    {
        $factures = $lot->getFacturesAssurance();
        $transaction = $this->findValidatedLotTransaction($lot);

        return [
            'id' => $lot->getId(),
            'description' => $lot->getDescription(),
            'statut' => $lot->getStatut(),
            'dateDebut' => $lot->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $lot->getDateFin()?->format('Y-m-d'),
            'dateCreation' => $lot->getDateCreation()?->format('Y-m-d H:i:s'),
            'dateEnvoi' => $lot->getDateEnvoi()?->format('Y-m-d H:i:s'),
            'dateRecouvrement' => $lot->getDateRecouvrement()?->format('Y-m-d H:i:s'),
            'nbFactures' => $factures->count(),
            'montantTotal' => $lot->computeMontantAssureur(),
            'assurance' => $this->mapAssuranceSummary($lot->getAssurance()),
            'transactionId' => $transaction?->getId(),
            'availableActions' => $this->resolveLotActions($lot, $transaction),
        ];
    }

    private function mapLotDetail(LotFactureAssurance $lot): array
    {
        $summary = $this->mapLotSummary($lot);
        $transaction = $this->findValidatedLotTransaction($lot);

        $factures = [];
        foreach ($lot->getFacturesAssurance() as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $totals = $facture->computeTotals();
            $patient = $facture->getConsultation()?->getPatient();
            $factures[] = [
                'id' => $facture->getId(),
                'patient' => $patient?->getFullName(),
                'telephone' => $patient?->getTelephone(),
                'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
                'montantTotal' => (float) ($totals['montantTotal'] ?? 0.0),
                'montantAssurance' => (float) ($totals['montantAssureur'] ?? 0.0),
                'montantPatient' => (float) ($totals['montantPatient'] ?? 0.0),
                'insuranceStatus' => $facture->getInsuranceStatus(),
                'tauxCouverture' => $facture->getCoverageRate(),
            ];
        }

        $summary['factures'] = $factures;
        $summary['transaction'] = $transaction ? [
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
        ] : null;

        return $summary;
    }

    private function resolveLotActions(LotFactureAssurance $lot, ?Transaction $transaction): array
    {
        $statut = $lot->getStatut();

        return [
            'canSend' => $statut === 'ouvert' && !$lot->getFacturesAssurance()->isEmpty(),
            'canRecover' => $statut === 'envoye' && !$transaction,
            'canCancelRecovery' => $statut === 'recouvre' && $transaction !== null,
            'canAddClaims' => $statut === 'ouvert',
            'canRemoveClaims' => $statut === 'ouvert',
        ];
    }
}

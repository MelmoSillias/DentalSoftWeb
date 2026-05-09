<?php

namespace App\Billing\Service;

use App\Billing\Dto\InsuranceClaimLineDto;
use App\Billing\Entity\Facture;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\FactureRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use Doctrine\ORM\EntityManagerInterface;

class InsuranceClaimService
{
    public function __construct(
        private FactureRepository $factureRepository,
        private ModeDePaiementRepository $modeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function listClaims(?string $status = null): array
    {
        $qb = $this->factureRepository->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('f.assurance', 'a')->addSelect('a')
            ->where('f.assurance IS NOT NULL')
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('f.insuranceStatus = :status')
                ->setParameter('status', $status);
        }

        return array_map(fn (Facture $facture): array => $this->mapClaim($facture), $qb->getQuery()->getResult());
    }

    public function validateClaim(int $factureId): array
    {
        $facture = $this->factureRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        if ($facture->getInsuranceStatus() === 'recouvre') {
            return ['error' => 'Créance déjà recouvrée', 'status' => 400];
        }

        $facture->setInsuranceStatus('validated');
        $this->em->flush();

        return ['success' => true];
    }

    public function rejectClaim(int $factureId, ?string $reason = null): array
    {
        $facture = $this->factureRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        if ($facture->isRecouvre()) {
            return ['error' => 'Impossible de rejeter une créance recouvrée', 'status' => 400];
        }

        $facture->setInsuranceStatus('rejected');
        if ($reason !== null && trim($reason) !== '') {
            $notes = trim((string) ($facture->getAssurance()?->getNotes() ?? ''));
            $next = $notes === '' ? ('Rejet créance: ' . $reason) : ($notes . PHP_EOL . 'Rejet créance: ' . $reason);
            $facture->getAssurance()?->setNotes($next);
        }

        $this->em->flush();

        return ['success' => true];
    }

    public function recoverClaim(int $factureId, int $modeId, ?\DateTimeInterface $date = null): array
    {
        $facture = $this->factureRepository->find($factureId);
        if (!$facture) {
            return ['error' => 'Facture introuvable', 'status' => 404];
        }

        if ($facture->getInsuranceStatus() !== 'validated') {
            return ['error' => 'La créance doit être validée avant recouvrement', 'status' => 400];
        }

        if ($facture->isRecouvre()) {
            return ['error' => 'Créance déjà recouvrée', 'status' => 400];
        }

        $mode = $this->modeRepository->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $consultation = $facture->getConsultation();
        $montant = $this->resolveClaimAmount($facture);
        if ($montant <= 0) {
            return ['error' => 'Montant assurance invalide', 'status' => 400];
        }

        $dateTransaction = $date ?? new \DateTimeImmutable();

        $transaction = new Transaction();
        $transaction->setType('Revenu');
        $transaction->setMontant((string) $montant);
        $transaction->setDateTransaction(\DateTime::createFromInterface($dateTransaction));
        $transaction->setDescription(sprintf('Recouvrement assurance | Facture #%d', $facture->getId()));
        $transaction->setModeDePaiement($mode);
        $transaction->setConsultation($consultation);
        $transaction->setRolePaiement('insurance');
        $transaction->setTauxPriseEnCharge($facture->getTauxCouverture());
        $transaction->markValidated();

        $facture->setIsRecouvre(true);
        $facture->setInsuranceStatus('recouvre');
        $consultation?->setIsRecouvre(true);
        $consultation?->setTauxCouverture($facture->getTauxCouverture());

        $this->em->persist($transaction);
        $this->em->flush();

        return ['success' => true, 'transactionId' => $transaction->getId()];
    }

    private function resolveClaimAmount(Facture $facture): float
    {
        $montants = $facture->computeMontantsFromConsultation();
        return max(0.0, (float) ($montants['montantAssurance'] ?? 0.0));
    }

    private function mapClaim(Facture $facture): array
    {
        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient();
        $status = $facture->getInsuranceStatus();
        $claimAmount = $this->resolveClaimAmount($facture);
        $montants = $facture->computeMontantsFromConsultation();

        return [
            'id' => $facture->getId(),
            'consultationId' => $consultation?->getId(),
            'dateFacture' => $facture->getDateFacture()?->format('Y-m-d H:i:s'),
            'patient' => $patient?->getFullName(),
            'telephone' => $patient?->getTelephone(),
            'assurance' => [
                'id' => $facture->getAssurance()?->getId(),
                'nom' => $facture->getAssurance()?->getNom(),
                'code' => $facture->getAssurance()?->getCode(),
            ],
            'tauxCouverture' => $facture->getTauxCouverture(),
            'montantTotal' => (float) ($montants['montantTotal'] ?? 0.0),
            'montantAssurance' => $claimAmount,
            'montantPatient' => (float) ($montants['montantPatient'] ?? 0.0),
            'restePatient' => (float) ($montants['restePatient'] ?? 0.0),
            'isRecouvre' => $facture->isRecouvre(),
            'insuranceStatus' => $status,
            'availableActions' => [
                'canValidate' => $status === 'pending',
                'canReject' => in_array($status, ['pending', 'validated'], true) && !$facture->isRecouvre(),
                'canRecover' => $status === 'validated' && !$facture->isRecouvre(),
            ],
            'lignes' => $this->buildClaimLines($facture),
        ];
    }

    private function buildClaimLines(Facture $facture): array
    {
        $lines = [];
        $consultation = $facture->getConsultation();

        foreach ($facture->buildLignesFromConsultation() as $line) {
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

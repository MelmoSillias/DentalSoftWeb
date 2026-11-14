<?php

namespace App\Billing\Application\UseCase;

use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\Transaction;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Infrastructure\Persistence\Doctrine\ModeDePaiementRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Billing\Application\Policy\TransactionTypePolicy;
use App\Billing\Application\Policy\ManualTransactionPolicy;
use App\Billing\Application\Mapper\FinanceTransactionMapper;
use App\Billing\Application\Service\LotFactureAssuranceService;

class ManageFinanceTransactions
{
    public function __construct(
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
        private EntityManagerInterface $em,
        private LotFactureAssuranceService $lotFactureAssuranceService,
        private FinanceTransactionMapper $transactionMapper,
        private TransactionTypePolicy $typePolicy,
        private ManualTransactionPolicy $manualPolicy,
    ) {
    }

    public function createTransaction(string $type, float $montant, ?string $description, DateTimeInterface $date, int $modeId, ?string $motif = null): array
    {
        $mode = $this->modeRepo->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $transaction = new Transaction();
        $transaction->setType($this->typePolicy->normalizePersistedTransactionType($type));
        $transaction->setMontant($montant);
        $transaction->setDescription($description);
        $transaction->setMotif($motif);
        $transaction->setDateTransaction($date);
        $transaction->setModeDePaiement($mode);

        $validatedAt = $date instanceof DateTimeImmutable
            ? $date
            : DateTimeImmutable::createFromInterface($date);
        $transaction->markValidated($validatedAt);

        $this->em->persist($transaction);
        $this->em->flush();

        return [
            'success' => true,
            'transactionId' => $transaction->getId(),
        ];
    }

    public function updateTransaction(int $id, string $type, float $montant, ?string $description, DateTimeInterface $date, int $modeId, ?string $motif = null): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if (!$this->manualPolicy->isManualTransaction($transaction)) {
            return ['error' => 'Seules les transactions ajoutées manuellement peuvent être modifiées.', 'status' => 400];
        }

        $mode = $this->modeRepo->find($modeId);
        if (!$mode) {
            return ['error' => 'Mode de paiement introuvable', 'status' => 400];
        }

        $transaction->setType($this->typePolicy->normalizePersistedTransactionType($type));
        $transaction->setMontant($montant);
        $transaction->setDescription($description);
        $transaction->setMotif($motif);
        $transaction->setDateTransaction($date);
        $transaction->setModeDePaiement($mode);

        if ($transaction->isValidated()) {
            $validatedAt = $date instanceof DateTimeImmutable
                ? $date
                : DateTimeImmutable::createFromInterface($date);
            $transaction->markValidated($validatedAt);
        }

        $this->em->flush();

        return [
            'success' => true,
            'transaction' => $this->transactionMapper->mapTransactionToArray($transaction),
        ];
    }

    public function transferInterCompte(int $fromId, int $toId, float $montant, string $motif, DateTimeInterface $date): array
    {
        $from = $this->modeRepo->find($fromId);
        $to = $this->modeRepo->find($toId);

        if (!$from || !$to || $from === $to) {
            return ['error' => 'Comptes invalides'];
        }

        $tOut = new Transaction();
        $tOut->setType('Depense');
        $tOut->setMontant($montant);
        $tOut->setDateTransaction($date);
        $tOut->setDescription("[Transfert] vers {$to->getLibelle()} - {$motif}");
        $tOut->setModeDePaiement($from);
        $validatedAt = $date instanceof DateTimeImmutable
            ? $date
            : DateTimeImmutable::createFromInterface($date);
        $tOut->markValidated($validatedAt);
        $this->em->persist($tOut);

        $tIn = new Transaction();
        $tIn->setType('Revenue');
        $tIn->setMontant($montant);
        $tIn->setDateTransaction($date);
        $tIn->setDescription("[Transfert] depuis {$from->getLibelle()} - {$motif}");
        $tIn->setModeDePaiement($to);
        $tIn->markValidated($validatedAt);
        $this->em->persist($tIn);

        $this->em->flush();

        return ['message' => 'Transfert effectué avec succÃ¨s'];
    }

    public function updateTransactionValidationStatus(int $id, string $status, ?string $comment = null, ?DateTimeImmutable $validatedAt = null): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if ($transaction->isSourceManaged()) return ['error' => 'Cette transaction se gère depuis la paie ou l’onglet INPS.', 'status' => 400];

        if ($status === 'validated') {
            $transaction->markValidated($validatedAt);
            $transaction->setValidationComment(null);

        } elseif ($status === 'rejected') {
            $devis = $this->detachTransactionPayment($transaction);
            $this->lotFactureAssuranceService->rollbackLotRecoveryFromTransaction($transaction);
            $transaction->markRejected($comment);
        } else {
            return ['error' => 'Statut de validation invalide', 'status' => 400];
        }

        $this->em->flush();

        return ['success' => true];
    }

    public function deleteTransaction(int $id): array
    {
        $transaction = $this->transactionRepo->find($id);
        if (!$transaction) {
            return ['error' => 'Transaction introuvable', 'status' => 404];
        }

        if ($transaction->isSourceManaged()) return ['error' => 'Cette trace de trésorerie doit être conservée. Utilisez la paie ou INPS.', 'status' => 400];

        $devis = $this->detachTransactionPayment($transaction);
        if ($devis === null) {
            $devis = $transaction->getFacture();
        }

        $this->lotFactureAssuranceService->rollbackLotRecoveryFromTransaction($transaction);
        $transaction->setConsultation(null);
        $transaction->setFacture(null);
        $transaction->setLotFactureAssurance(null);
        $this->em->remove($transaction);
        $this->em->flush();

        return ['success' => true];
    }

    private function detachTransactionPayment(Transaction $transaction): ?Facture
    {
        $paiement = $transaction->getPaiement();
        $facture = $transaction->getFacture();

        if (!$paiement instanceof Paiement) {
            return $facture;
        }

        $facture = $facture ?: $paiement->getFacture();
        $consultation = $transaction->getConsultation();

        if ($consultation && $consultation->getPaiement() === $paiement) {
            $consultation->setPaiement(null);
        }

        if ($facture && $facture->getPaiements()->contains($paiement)) {
            $facture->removePaiement($paiement);
        }

        $transaction->setPaiement(null);
        $paiement->setConsultation(null);
        $paiement->setFacture(null);
        $paiement->setFactureAssurance(null);
        $this->em->remove($paiement);

        return $facture;
    }
}

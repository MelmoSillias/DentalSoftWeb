<?php

namespace App\Billing\Application\Mapper;

use App\Billing\Domain\Entity\Transaction;
use App\Billing\Application\Policy\TransactionTypePolicy;
use App\Billing\Application\Policy\ManualTransactionPolicy;

class FinanceTransactionMapper
{
    public function __construct(
        private TransactionTypePolicy $typePolicy,
        private ManualTransactionPolicy $manualPolicy,
    ) {
    }

    public function mapTransactionToArray(Transaction $transaction): array
    {
        $typeKey = $this->typePolicy->resolveTransactionTypeKey($transaction->getType());
        $lot = $transaction->getLotFactureAssurance();
        $isManual = $this->manualPolicy->isManualTransaction($transaction);

        return [
            'date' => $transaction->getDateTransaction()->format('Y-m-d'),
            'dateTransaction' => $transaction->getDateTransaction()->format('Y-m-d H:i:s'),
            'id' => $transaction->getId(),
            'description' => $transaction->getDescription(),
            'motif' => $transaction->getMotif(),
            'type' => $transaction->getType(),
            'typeKey' => $typeKey,
            'typeLabel' => $typeKey === 'revenue' ? 'Revenu' : ($typeKey === 'expense' ? 'Dépense' : ($transaction->getType() ?? '')),
            'amount' => $transaction->getMontant(),
            'validated' => $transaction->isValidated(),
            'validationStatus' => $transaction->getValidationStatus(),
            'validationComment' => $transaction->getValidationComment(),
            'validatedAt' => $transaction->getValidatedAt()?->format(DATE_ATOM),
            'rolePaiement' => $transaction->getRolePaiement(),
            'isManual' => $isManual,
            'canEdit' => $isManual,
            'sourceManaged' => $transaction->isSourceManaged(),
            'lotFactureAssurance' => $lot ? [
                'id' => $lot->getId(),
                'description' => $lot->getDescription(),
                'statut' => $lot->getStatut(),
                'assurance' => [
                    'id' => $lot->getAssurance()?->getId(),
                    'nom' => $lot->getAssurance()?->getNom(),
                    'code' => $lot->getAssurance()?->getCode(),
                ],
            ] : null,
            'modeDePaiement' => [
                'id' => $transaction->getModeDePaiement()->getId(),
                'libelle' => $transaction->getModeDePaiement()->getLibelle(),
                'type' => $transaction->getModeDePaiement()->getType(),
            ],
        ];
    }
}

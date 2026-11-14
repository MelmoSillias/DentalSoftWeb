<?php

namespace App\Billing\Application\Policy;

use App\Billing\Domain\Entity\Transaction;

class ManualTransactionPolicy
{
    public function isManualTransaction(Transaction $transaction): bool
    {
        return !$transaction->isSourceManaged() && $transaction->getFacture() === null
            && $transaction->getPaiement() === null
            && $transaction->getConsultation() === null
            && $transaction->getLotFactureAssurance() === null;
    }
}

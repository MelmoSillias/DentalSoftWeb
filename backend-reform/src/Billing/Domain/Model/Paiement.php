<?php

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\ValueObject\PaiementId;

/**
 * Lightweight payment identity for strangler cutover (no full aggregate yet).
 */
final class Paiement
{
    private function __construct(
        private ?PaiementId $id,
        private float $amount,
    ) {
        if ($this->amount <= 0) {
            throw new BillingDomainException('Paiement amount must be greater than zero.');
        }
    }

    public static function create(float $amount): self
    {
        return new self(null, $amount);
    }

    public static function reconstitute(PaiementId $id, float $amount): self
    {
        return new self($id, $amount);
    }

    public function getId(): ?PaiementId
    {
        return $this->id;
    }

    public function requireId(): PaiementId
    {
        if ($this->id === null) {
            throw new BillingDomainException('Paiement id is not assigned.');
        }

        return $this->id;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }
}

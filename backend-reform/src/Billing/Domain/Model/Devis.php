<?php

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\ValueObject\DevisId;

/**
 * Minimal devis aggregate for strangler cutover.
 *
 * Legacy statut codes observed in storage / UI payloads:
 *  0  = draft (open)
 *  1  = validated
 * -1  = cancelled (domain convention persisted via statut)
 */
final class Devis
{
    public const STATUS_DRAFT = 0;
    public const STATUS_VALIDATED = 1;
    public const STATUS_CANCELLED = -1;

    private function __construct(
        private ?DevisId $id,
        private int $status,
    ) {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_VALIDATED, self::STATUS_CANCELLED], true)) {
            throw new BillingDomainException('Devis status is invalid.');
        }
    }

    public static function reconstitute(DevisId $id, int $status, bool $cancelled = false): self
    {
        if ($cancelled && $status !== self::STATUS_CANCELLED) {
            $status = self::STATUS_CANCELLED;
        }

        return new self($id, $status);
    }

    public function validate(): void
    {
        if ($this->isCancelled()) {
            throw new BillingDomainException('Cancelled devis cannot be validated.');
        }

        if ($this->isValidated()) {
            throw new BillingDomainException('Devis is already validated.');
        }

        if ($this->status !== self::STATUS_DRAFT) {
            throw new BillingDomainException('Only a draft devis can be validated.');
        }

        $this->status = self::STATUS_VALIDATED;
    }

    public function cancel(): void
    {
        if ($this->isCancelled()) {
            throw new BillingDomainException('Devis is already cancelled.');
        }

        $this->status = self::STATUS_CANCELLED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getId(): ?DevisId
    {
        return $this->id;
    }

    public function requireId(): DevisId
    {
        if ($this->id === null) {
            throw new BillingDomainException('Devis id is not assigned.');
        }

        return $this->id;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}

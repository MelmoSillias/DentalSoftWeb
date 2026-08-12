<?php

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\ValueObject\LotFactureAssuranceId;
use App\Billing\Domain\ValueObject\LotStatus;

/**
 * Minimal insurance lot aggregate for strangler cutover.
 *
 * Domain owns ouvert ↔ envoye ↔ confirme. Refund statuses are
 * reconstituted from persistence without domain transition.
 */
final class LotFactureAssurance
{
    private function __construct(
        private ?LotFactureAssuranceId $id,
        private LotStatus $status,
    ) {
    }

    public static function reconstitute(LotFactureAssuranceId $id, string $status): self
    {
        return new self($id, LotStatus::fromString($status));
    }

    public function send(): void
    {
        $this->status = $this->status->send();
    }

    public function reopen(): void
    {
        $this->status = $this->status->reopen();
    }

    public function confirm(): void
    {
        $this->status = $this->status->confirm();
    }

    public function unconfirm(): void
    {
        $this->status = $this->status->unconfirm();
    }

    public function isOuvert(): bool
    {
        return $this->status->isOuvert();
    }

    public function isEnvoye(): bool
    {
        return $this->status->isEnvoye();
    }

    public function isConfirme(): bool
    {
        return $this->status->isConfirme();
    }

    public function getId(): ?LotFactureAssuranceId
    {
        return $this->id;
    }

    public function requireId(): LotFactureAssuranceId
    {
        if ($this->id === null) {
            throw new BillingDomainException('LotFactureAssurance id is not assigned.');
        }

        return $this->id;
    }

    public function getStatus(): LotStatus
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->toString();
    }
}

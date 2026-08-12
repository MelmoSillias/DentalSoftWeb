<?php

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Exception\BillingDomainException;
use App\Billing\Domain\ValueObject\FactureAssuranceId;
use App\Billing\Domain\ValueObject\InsuranceStatus;

/**
 * Minimal insurance claim aggregate for strangler cutover.
 *
 * Domain owns pending → ready. Other statuses are application/billing policy
 * (see InsuranceStatus docblock) and may be reconstituted without transition.
 */
final class FactureAssurance
{
    private function __construct(
        private ?FactureAssuranceId $id,
        private InsuranceStatus $insuranceStatus,
    ) {
    }

    public static function reconstitute(FactureAssuranceId $id, string $insuranceStatus): self
    {
        return new self($id, InsuranceStatus::fromString($insuranceStatus));
    }

    public function markReady(): void
    {
        $this->insuranceStatus = $this->insuranceStatus->markReady();
    }

    public function isPending(): bool
    {
        return $this->insuranceStatus->isPending();
    }

    public function isReady(): bool
    {
        return $this->insuranceStatus->isReady();
    }

    public function getId(): ?FactureAssuranceId
    {
        return $this->id;
    }

    public function requireId(): FactureAssuranceId
    {
        if ($this->id === null) {
            throw new BillingDomainException('FactureAssurance id is not assigned.');
        }

        return $this->id;
    }

    public function getInsuranceStatus(): InsuranceStatus
    {
        return $this->insuranceStatus;
    }

    public function getInsuranceStatusValue(): string
    {
        return $this->insuranceStatus->toString();
    }
}

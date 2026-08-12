<?php

namespace App\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;

/**
 * Value object representing a medical act line on a consultation.
 */
final class ActeMedical
{
    public function __construct(
        private readonly string $type,
        private readonly ?string $dent,
        private readonly ?string $description,
        private readonly float $prix,
        private readonly int $quantite,
    ) {
        if (trim($this->type) === '') {
            throw new CareDeliveryDomainException('ActeMedical type cannot be empty.');
        }

        if ($this->quantite <= 0) {
            throw new CareDeliveryDomainException('ActeMedical quantity must be positive.');
        }

        if ($this->prix < 0) {
            throw new CareDeliveryDomainException('ActeMedical price cannot be negative.');
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDent(): ?string
    {
        return $this->dent;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }
}

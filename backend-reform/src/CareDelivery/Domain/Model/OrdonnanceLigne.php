<?php

namespace App\CareDelivery\Domain\Model;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;

/**
 * Value object for a prescription line.
 */
final class OrdonnanceLigne
{
    public function __construct(
        private readonly string $designation,
        private readonly ?string $posologie = null,
        private readonly ?string $frequence = null,
        private readonly ?string $duree = null,
        private readonly ?int $quantite = null,
        private readonly ?string $instructions = null,
    ) {
        if (trim($this->designation) === '') {
            throw new CareDeliveryDomainException('Ordonnance line designation cannot be empty.');
        }

        if ($this->quantite !== null && $this->quantite <= 0) {
            throw new CareDeliveryDomainException('Ordonnance line quantity must be positive when set.');
        }
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function getPosologie(): ?string
    {
        return $this->posologie;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }
}

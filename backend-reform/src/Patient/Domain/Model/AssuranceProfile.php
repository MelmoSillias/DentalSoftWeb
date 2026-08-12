<?php

namespace App\Patient\Domain\Model;

final class AssuranceProfile
{
    public function __construct(
        private ?int $id,
        private ?int $assuranceId,
        private ?string $numeroAssure,
        private ?string $numeroAffiliation,
        private ?float $tauxCouverture,
    ) {
    }

    public static function create(
        ?int $assuranceId,
        ?string $numeroAssure,
        ?string $numeroAffiliation,
        ?float $tauxCouverture,
    ): self {
        return new self(null, $assuranceId, $numeroAssure, $numeroAffiliation, $tauxCouverture);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssuranceId(): ?int
    {
        return $this->assuranceId;
    }

    public function getNumeroAssure(): ?string
    {
        return $this->numeroAssure;
    }

    public function getNumeroAffiliation(): ?string
    {
        return $this->numeroAffiliation;
    }

    public function getTauxCouverture(): ?float
    {
        return $this->tauxCouverture;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->assuranceId, $this->numeroAssure, $this->numeroAffiliation, $this->tauxCouverture);
    }

    public function isEmpty(): bool
    {
        return $this->assuranceId === null
            && ($this->numeroAssure === null || $this->numeroAssure === '')
            && ($this->numeroAffiliation === null || $this->numeroAffiliation === '')
            && $this->tauxCouverture === null;
    }
}

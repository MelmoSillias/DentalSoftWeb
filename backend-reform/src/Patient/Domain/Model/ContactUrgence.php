<?php

namespace App\Patient\Domain\Model;

final class ContactUrgence
{
    public function __construct(
        private ?int $id,
        private ?string $nom,
        private ?string $telephone,
        private ?string $lienParente,
    ) {
    }

    public static function create(?string $nom, ?string $telephone, ?string $lienParente): self
    {
        return new self(null, $nom, $telephone, $lienParente);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function getLienParente(): ?string
    {
        return $this->lienParente;
    }

    public function isEmpty(): bool
    {
        return ($this->nom === null || $this->nom === '')
            && ($this->telephone === null || $this->telephone === '')
            && ($this->lienParente === null || $this->lienParente === '');
    }

    public function update(?string $nom, ?string $telephone, ?string $lienParente): self
    {
        return new self($this->id, $nom, $telephone, $lienParente);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->nom, $this->telephone, $this->lienParente);
    }
}

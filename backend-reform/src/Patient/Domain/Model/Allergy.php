<?php

namespace App\Patient\Domain\Model;

use App\Patient\Domain\Exception\PatientDomainException;

final class Allergy
{
    public function __construct(
        private ?int $id,
        private string $libelle,
        private ?string $description,
    ) {
        if (trim($libelle) === '') {
            throw new PatientDomainException('Allergy libelle is required.');
        }
    }

    public static function create(string $libelle, ?string $description = null): self
    {
        return new self(null, trim($libelle), $description);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->libelle, $this->description);
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new PatientDomainException('Allergy already has an id.');
        }
        $this->id = $id;
    }
}

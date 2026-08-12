<?php

namespace App\Patient\Domain\Model;

use App\Patient\Domain\Exception\PatientDomainException;
use DateTimeImmutable;

final class Antecedent
{
    public function __construct(
        private ?int $id,
        private ?string $description,
        private ?string $type,
        private DateTimeImmutable $dateEnregistrement,
    ) {
    }

    public static function create(?string $description, ?string $type, ?DateTimeImmutable $at = null): self
    {
        return new self(null, $description, $type, $at ?? new DateTimeImmutable());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getDateEnregistrement(): DateTimeImmutable
    {
        return $this->dateEnregistrement;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->description, $this->type, $this->dateEnregistrement);
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new PatientDomainException('Antecedent already has an id.');
        }
        $this->id = $id;
    }
}

<?php

namespace App\ClinicalRecord\Domain\Model;

use App\ClinicalRecord\Domain\Exception\ClinicalRecordDomainException;
use App\ClinicalRecord\Domain\ValueObject\FicheMedicaleId;

final class FicheMedicale
{
    private function __construct(
        private ?FicheMedicaleId $id,
        private int $patientId,
        private bool $archived,
    ) {
        if ($this->patientId <= 0) {
            throw new ClinicalRecordDomainException('FicheMedicale requires a valid patientId.');
        }
    }

    public static function reconstitute(FicheMedicaleId $id, int $patientId, bool $archived = false): self
    {
        return new self($id, $patientId, $archived);
    }

    public function archive(): void
    {
        if ($this->archived) {
            throw new ClinicalRecordDomainException('Fiche medicale is already archived.');
        }
        $this->archived = true;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function getId(): ?FicheMedicaleId
    {
        return $this->id;
    }

    public function requireId(): FicheMedicaleId
    {
        if ($this->id === null) {
            throw new ClinicalRecordDomainException('FicheMedicale id is not assigned.');
        }

        return $this->id;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }
}

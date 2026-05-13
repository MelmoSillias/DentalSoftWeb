<?php

namespace App\Patient\Entity;

use App\Billing\Entity\Assurance;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PatientAssuranceProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'assuranceProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Assurance::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Assurance $assurance = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $coverageRate = null;

    #[ORM\Column(type: Types::JSON)]
    private array $formData = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        $this->touch();

        return $this;
    }

    public function getAssurance(): ?Assurance
    {
        return $this->assurance;
    }

    public function setAssurance(?Assurance $assurance): static
    {
        $this->assurance = $assurance;
        $this->touch();

        return $this;
    }

    public function getCoverageRate(): ?float
    {
        return $this->coverageRate;
    }

    public function setCoverageRate(?float $coverageRate): static
    {
        $this->coverageRate = $coverageRate;
        $this->touch();

        return $this;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function setFormData(array $formData): static
    {
        $this->formData = $formData;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

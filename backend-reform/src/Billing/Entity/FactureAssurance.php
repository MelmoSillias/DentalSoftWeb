<?php

namespace App\Billing\Entity;

use App\Billing\Repository\FactureAssuranceRepository;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use App\Patient\Entity\Patient;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureAssuranceRepository::class)]
class FactureAssurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'factureAssurance')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Assurance::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Assurance $assurance = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $coverageRate = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $consultationAmount = 0.0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isConsultationPayante = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $insuranceStatus = 'pending';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRecouvre = false;

    #[ORM\Column(type: Types::JSON)]
    private array $assuranceSnapshot = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getAssurance(): ?Assurance
    {
        return $this->assurance;
    }

    public function setAssurance(?Assurance $assurance): static
    {
        $this->assurance = $assurance;

        return $this;
    }

    public function getCoverageRate(): ?float
    {
        return $this->coverageRate;
    }

    public function setCoverageRate(?float $coverageRate): static
    {
        $this->coverageRate = $coverageRate;

        return $this;
    }

    public function getConsultationAmount(): float
    {
        return $this->consultationAmount;
    }

    public function setConsultationAmount(float $consultationAmount): static
    {
        $this->consultationAmount = max(0.0, $consultationAmount);

        return $this;
    }

    public function isConsultationPayante(): bool
    {
        return $this->isConsultationPayante;
    }

    public function setIsConsultationPayante(bool $isConsultationPayante): static
    {
        $this->isConsultationPayante = $isConsultationPayante;

        return $this;
    }

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(?\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getInsuranceStatus(): string
    {
        return $this->insuranceStatus;
    }

    public function setInsuranceStatus(string $insuranceStatus): static
    {
        $this->insuranceStatus = $insuranceStatus;

        return $this;
    }

    public function isRecouvre(): bool
    {
        return $this->isRecouvre;
    }

    public function setIsRecouvre(bool $isRecouvre): static
    {
        $this->isRecouvre = $isRecouvre;

        return $this;
    }

    public function getAssuranceSnapshot(): array
    {
        return $this->assuranceSnapshot;
    }

    public function setAssuranceSnapshot(array $assuranceSnapshot): static
    {
        $this->assuranceSnapshot = $assuranceSnapshot;

        return $this;
    }

    public function buildLignes(): array
    {
        $consultation = $this->getConsultation();
        if (!$consultation) {
            return [];
        }

        $lines = [];
        if ($this->isConsultationPayante() && $this->consultationAmount > 0) {
            $lines[] = [
                'designation' => 'Consultation orthodontique',
                'description' => 'Consultation orthodontique',
                'quantite' => 1,
                'prix' => (float) $this->consultationAmount,
                'total' => (float) $this->consultationAmount,
            ];
        }

        foreach ($consultation->getActes() as $acte) {
            if (!$acte instanceof ActeMedical) {
                continue;
            }

            $type = trim((string) ($acte->getType() ?? ''));
            $description = trim((string) ($acte->getDescription() ?? ''));
            $designation = $type !== '' ? $type : ($description !== '' ? $description : 'Acte');
            $quantite = max(1, (int) ($acte->getQuantite() ?? 1));
            $prix = max(0.0, (float) ($acte->getPrix() ?? 0));

            $lines[] = [
                'designation' => $designation,
                'description' => $description !== '' ? $description : $designation,
                'quantite' => $quantite,
                'prix' => $prix,
                'total' => $quantite * $prix,
            ];
        }

        return $lines;
    }

    public function computeTotals(): array
    {
        $total = 0.0;
        foreach ($this->buildLignes() as $line) {
            $total += (float) ($line['total'] ?? 0.0);
        }

        $rate = $this->coverageRate === null ? null : max(0.0, min(100.0, (float) $this->coverageRate));
        $assureur = $rate === null ? 0.0 : max(0.0, min($total, ($total * $rate) / 100));
        $patient = max(0.0, $total - $assureur);

        return [
            'montantTotal' => $total,
            'montantAssureur' => $assureur,
            'montantPatient' => $patient,
            'tauxCouverture' => $rate,
        ];
    }
}

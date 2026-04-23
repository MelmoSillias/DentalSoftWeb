<?php

namespace App\Billing\Entity;

use App\Billing\Repository\ModeDePaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeDePaiementRepository::class)]
class ModeDePaiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeKey = null;

    #[ORM\Column(length: 20, options: ['default' => 'classic'])]
    private string $familyKey = 'classic';

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $coverageRate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'modeDePaiement', targetEntity: Transaction::class)]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeKey(): ?string
    {
        return $this->typeKey;
    }

    public function setTypeKey(?string $typeKey): static
    {
        $this->typeKey = $typeKey;
        return $this;
    }

    public function getFamilyKey(): string
    {
        return $this->familyKey;
    }

    public function setFamilyKey(string $familyKey): static
    {
        $this->familyKey = $familyKey;
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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function isInsurance(): bool
    {
        if ($this->familyKey === 'insurance') {
            return true;
        }

        return str_contains(strtolower((string) $this->type), 'assur');
    }

    public function isAutoValidated(): bool
    {
        if (in_array($this->typeKey, ['cash', 'mobile_money'], true)) {
            return true;
        }

        $normalizedType = strtolower((string) $this->type);
        return str_contains($normalizedType, 'esp') || str_contains($normalizedType, 'cash') || (str_contains($normalizedType, 'mobile') && str_contains($normalizedType, 'money'));
    }
}
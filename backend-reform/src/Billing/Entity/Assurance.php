<?php

namespace App\Billing\Entity;

use App\Billing\Repository\AssuranceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssuranceRepository::class)]
class Assurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $tauxParDefaut = null;

    #[ORM\OneToMany(mappedBy: 'assurance', targetEntity: Facture::class)]
    private Collection $factures;

    public function __construct()
    {
        $this->factures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    public function getTauxParDefaut(): ?float
    {
        return $this->tauxParDefaut;
    }

    public function setTauxParDefaut(?float $tauxParDefaut): static
    {
        $this->tauxParDefaut = $tauxParDefaut;

        return $this;
    }

    public function getFactures(): Collection
    {
        return $this->factures;
    }
}

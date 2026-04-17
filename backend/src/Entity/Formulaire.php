<?php

namespace App\Entity;

use App\Repository\FormulaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormulaireRepository::class)]
#[ORM\Table(name: 'formulaire')]
#[ORM\UniqueConstraint(name: 'uniq_formulaire_code', fields: ['code'])]
class Formulaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isNatif = false;

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'formulaire', targetEntity: FormulaireOnglet::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    private Collection $onglets;

    #[ORM\OneToMany(mappedBy: 'formulaire', targetEntity: FicheMedicale::class)]
    private Collection $fichesMedicales;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->onglets = new ArrayCollection();
        $this->fichesMedicales = new ArrayCollection();
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isNatif(): bool
    {
        return $this->isNatif;
    }

    public function setIsNatif(bool $isNatif): static
    {
        $this->isNatif = $isNatif;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getOnglets(): Collection
    {
        return $this->onglets;
    }

    public function addOnglet(FormulaireOnglet $onglet): static
    {
        if (!$this->onglets->contains($onglet)) {
            $this->onglets->add($onglet);
            $onglet->setFormulaire($this);
            $this->touch();
        }

        return $this;
    }

    public function removeOnglet(FormulaireOnglet $onglet): static
    {
        if ($this->onglets->removeElement($onglet) && $onglet->getFormulaire() === $this) {
            $onglet->setFormulaire(null);
            $this->touch();
        }

        return $this;
    }
}
<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'formulaire_onglet')]
#[ORM\UniqueConstraint(name: 'uniq_formulaire_onglet_code', fields: ['formulaire', 'code'])]
class FormulaireOnglet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Formulaire::class, inversedBy: 'onglets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Formulaire $formulaire = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\OneToMany(mappedBy: 'onglet', targetEntity: FormulaireSection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    private Collection $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormulaire(): ?Formulaire
    {
        return $this->formulaire;
    }

    public function setFormulaire(?Formulaire $formulaire): static
    {
        $this->formulaire = $formulaire;

        return $this;
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

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(FormulaireSection $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setOnglet($this);
        }

        return $this;
    }

    public function removeSection(FormulaireSection $section): static
    {
        if ($this->sections->removeElement($section) && $section->getOnglet() === $this) {
            $section->setOnglet(null);
        }

        return $this;
    }
}
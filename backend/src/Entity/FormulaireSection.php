<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'formulaire_section')]
#[ORM\UniqueConstraint(name: 'uniq_formulaire_section_code', fields: ['onglet', 'code'])]
class FormulaireSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FormulaireOnglet::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FormulaireOnglet $onglet = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\OneToMany(mappedBy: 'section', targetEntity: FormulaireChamp::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    private Collection $champs;

    public function __construct()
    {
        $this->champs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOnglet(): ?FormulaireOnglet
    {
        return $this->onglet;
    }

    public function setOnglet(?FormulaireOnglet $onglet): static
    {
        $this->onglet = $onglet;

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

    public function getChamps(): Collection
    {
        return $this->champs;
    }

    public function addChamp(FormulaireChamp $champ): static
    {
        if (!$this->champs->contains($champ)) {
            $this->champs->add($champ);
            $champ->setSection($this);
        }

        return $this;
    }

    public function removeChamp(FormulaireChamp $champ): static
    {
        if ($this->champs->removeElement($champ) && $champ->getSection() === $this) {
            $champ->setSection(null);
        }

        return $this;
    }
}
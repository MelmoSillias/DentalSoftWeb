<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'formulaire_champ')]
#[ORM\UniqueConstraint(name: 'uniq_formulaire_champ_code', fields: ['section', 'code'])]
class FormulaireChamp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FormulaireSection::class, inversedBy: 'champs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FormulaireSection $section = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(type: Types::JSON)]
    private array $config = [];

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\OneToMany(mappedBy: 'champ', targetEntity: FicheMedicaleValeur::class)]
    private Collection $valeurs;

    public function __construct()
    {
        $this->valeurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): ?FormulaireSection
    {
        return $this->section;
    }

    public function setSection(?FormulaireSection $section): static
    {
        $this->section = $section;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;

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

    public function getValeurs(): Collection
    {
        return $this->valeurs;
    }
}
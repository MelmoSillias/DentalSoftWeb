<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'fiche_medicale_valeur')]
#[ORM\Index(name: 'idx_fiche_medicale_valeur_fiche', columns: ['fiche_medicale_id'])]
#[ORM\Index(name: 'idx_fiche_medicale_valeur_champ_code', columns: ['champ_code'])]
class FicheMedicaleValeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheMedicale::class, inversedBy: 'valeursDynamiques')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\ManyToOne(targetEntity: FormulaireChamp::class, inversedBy: 'valeurs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FormulaireChamp $champ = null;

    #[ORM\Column(length: 120)]
    private string $champCode;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $valeur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFicheMedicale(): ?FicheMedicale
    {
        return $this->ficheMedicale;
    }

    public function setFicheMedicale(?FicheMedicale $ficheMedicale): static
    {
        $this->ficheMedicale = $ficheMedicale;

        return $this;
    }

    public function getChamp(): ?FormulaireChamp
    {
        return $this->champ;
    }

    public function setChamp(?FormulaireChamp $champ): static
    {
        $this->champ = $champ;

        return $this;
    }

    public function getChampCode(): string
    {
        return $this->champCode;
    }

    public function setChampCode(string $champCode): static
    {
        $this->champCode = $champCode;

        return $this;
    }

    public function getValeur(): mixed
    {
        return $this->valeur;
    }

    public function setValeur(mixed $valeur): static
    {
        $this->valeur = $valeur;
        $this->updatedAt = new \DateTimeImmutable();

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
}
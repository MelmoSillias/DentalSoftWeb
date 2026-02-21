<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheExamenItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheExamen $examen = null;

    #[ORM\Column(length: 50)]
    private ?string $categorie = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $estPresent = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExamen(): ?FicheExamen
    {
        return $this->examen;
    }

    public function setExamen(?FicheExamen $examen): static
    {
        $this->examen = $examen;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getEstPresent(): ?bool
    {
        return $this->estPresent;
    }

    public function setEstPresent(?bool $estPresent): static
    {
        $this->estPresent = $estPresent;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;
        return $this;
    }
}

<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheEntretienAffection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'affections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheEntretien $entretien = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $estPresente = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntretien(): ?FicheEntretien
    {
        return $this->entretien;
    }

    public function setEntretien(?FicheEntretien $entretien): static
    {
        $this->entretien = $entretien;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getEstPresente(): ?bool
    {
        return $this->estPresente;
    }

    public function setEstPresente(?bool $estPresente): static
    {
        $this->estPresente = $estPresente;
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

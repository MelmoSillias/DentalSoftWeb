<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheEntretienMedicament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'medicaments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheEntretien $entretien = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $estUtilise = null;

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

    public function getEstUtilise(): ?bool
    {
        return $this->estUtilise;
    }

    public function setEstUtilise(?bool $estUtilise): static
    {
        $this->estUtilise = $estUtilise;
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

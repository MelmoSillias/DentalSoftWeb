<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheEntretienHabitude
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'habitudes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheEntretien $entretien = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $estPresente = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $quantite = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
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

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }
}

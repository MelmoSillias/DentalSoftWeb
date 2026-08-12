<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\OrdonnanceLigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdonnanceLigneRepository::class)]
class OrdonnanceLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ordonnance::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ordonnance $ordonnance = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $designation = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $posologie = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $frequence = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $duree = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrdonnance(): ?Ordonnance
    {
        return $this->ordonnance;
    }

    public function setOrdonnance(?Ordonnance $ordonnance): self
    {
        $this->ordonnance = $ordonnance;
        return $this;
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): self
    {
        $this->designation = $designation;
        return $this;
    }

    public function getPosologie(): ?string
    {
        return $this->posologie;
    }

    public function setPosologie(?string $posologie): self
    {
        $this->posologie = $posologie;
        return $this;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function setFrequence(?string $frequence): self
    {
        $this->frequence = $frequence;
        return $this;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function setDuree(?string $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }
}

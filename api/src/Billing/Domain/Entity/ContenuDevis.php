<?php

namespace App\Billing\Domain\Entity;

use App\Billing\Infrastructure\Persistence\Doctrine\ContenuDevisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContenuDevisRepository::class)]
class ContenuDevis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: 'contenus')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(name: 'designation', type: 'string', length: 255)]
    private ?string $traitement = null;

    #[ORM\Column(type: 'integer')]
    private int $qte;

    #[ORM\Column(type: 'float')]
    private float $montant;

    #[ORM\Column(type: 'float')]
    private float $montantTotal;

    public function getId(): ?int { return $this->id; }
    public function getDevis(): ?Devis { return $this->devis; }
    public function setDevis(?Devis $devis): self { $this->devis = $devis; return $this; }
    public function getDiagnostic(): ?string { return $this->diagnostic; }
    public function setDiagnostic(?string $diagnostic): self { $this->diagnostic = $diagnostic; return $this; }
    public function getTraitement(): ?string { return $this->traitement; }
    public function setTraitement(string $traitement): self { $this->traitement = $traitement; return $this; }
    public function getQte(): int { return $this->qte; }
    public function setQte(int $qte): self { $this->qte = $qte; return $this; }
    public function getMontant(): float { return $this->montant; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }
    public function getMontantTotal(): float { return $this->montantTotal; }
    public function setMontantTotal(float $montantTotal): self { $this->montantTotal = $montantTotal; return $this; }
}
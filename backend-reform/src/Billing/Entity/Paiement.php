<?php

namespace App\Billing\Entity;

use App\Billing\Repository\PaiementRepository;
use App\CareDelivery\Entity\Consultation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ModeDePaiement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ModeDePaiement $mode = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'float')]
    private float $montant;

    #[ORM\OneToOne(targetEntity: Consultation::class, inversedBy: 'paiement')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Facture $facture = null;
  
    #[ORM\OneToOne(mappedBy: 'paiement', cascade: ['persist', 'remove'])]
    private ?Transaction $transaction = null;

    public function getId(): ?int { return $this->id; } 
    public function getMode(): ?ModeDePaiement { return $this->mode; }
    public function setMode(?ModeDePaiement $mode): self { $this->mode = $mode; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getMontant(): float { return $this->montant; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        if ($transaction === null && $this->transaction !== null) {
            $this->transaction->setPaiement(null);
        }

        if ($transaction !== null && $transaction->getPaiement() !== $this) {
            $transaction->setPaiement($this);
        }

        $this->transaction = $transaction;

        return $this;
    }
}

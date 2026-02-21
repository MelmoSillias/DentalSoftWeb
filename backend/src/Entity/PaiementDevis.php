<?php

namespace App\Entity;

use App\Repository\PaiementDevisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementDevisRepository::class)]
class PaiementDevis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(targetEntity: ModeDePaiement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ModeDePaiement $mode = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'float')]
    private float $montant;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Consultation $consultation = null;

    #[ORM\OneToOne(mappedBy: 'paiementDevis', cascade: ['persist', 'remove'])]
    private ?Transaction $transaction = null;

    public function getId(): ?int { return $this->id; }
    public function getDevis(): ?Devis { return $this->devis; }
    public function setDevis(?Devis $devis): self { $this->devis = $devis; return $this; }
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

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        // unset the owning side of the relation if necessary
        if ($transaction === null && $this->transaction !== null) {
            $this->transaction->setPaiementDevis(null);
        }

        // set the owning side of the relation if necessary
        if ($transaction !== null && $transaction->getPaiementDevis() !== $this) {
            $transaction->setPaiementDevis($this);
        }

        $this->transaction = $transaction;

        return $this;
    }
}

<?php

namespace App\Billing\Entity;

use App\Billing\Repository\FactureCabinetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureCabinetRepository::class)]
#[ORM\Table(name: 'facture_cabinet')]
class FactureCabinet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'facture', targetEntity: ServiceCabinet::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceCabinet $service = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $montant = 0.0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isReglee = false;

    /** @var Collection<int, Paiement> */
    #[ORM\OneToMany(mappedBy: 'factureCabinet', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): ?ServiceCabinet
    {
        return $this->service;
    }

    public function setService(?ServiceCabinet $service): static
    {
        $this->service = $service;
        if ($service !== null && $service->getFacture() !== $this) {
            $service->setFacture($this);
        }

        return $this;
    }

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getMontant(): float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = max(0.0, $montant);

        return $this;
    }

    public function isReglee(): bool
    {
        return $this->isReglee;
    }

    public function setIsReglee(bool $isReglee): static
    {
        $this->isReglee = $isReglee;

        return $this;
    }

    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setFactureCabinet($this);
        }

        return $this;
    }

    public function removePaiement(Paiement $paiement): static
    {
        if ($this->paiements->removeElement($paiement) && $paiement->getFactureCabinet() === $this) {
            $paiement->setFactureCabinet(null);
        }

        return $this;
    }

    public function computePaidAmount(): float
    {
        $paid = 0.0;
        foreach ($this->paiements as $payment) {
            $status = $payment->getTransaction()?->getValidationStatus();
            if ($status !== null && $status !== 'validated') {
                continue;
            }
            $paid += (float) $payment->getMontant();
        }

        return round($paid, 2);
    }

    public function computeReste(): float
    {
        return round(max(0.0, $this->montant - $this->computePaidAmount()), 2);
    }
}

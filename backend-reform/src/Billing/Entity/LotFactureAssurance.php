<?php

namespace App\Billing\Entity;

use App\Billing\Repository\LotFactureAssuranceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotFactureAssuranceRepository::class)]
class LotFactureAssurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Assurance::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Assurance $assurance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 20, options: ['default' => 'ouvert'])]
    private string $statut = 'ouvert';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRecouvrement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, FactureAssurance> */
    #[ORM\OneToMany(mappedBy: 'lotFactureAssurance', targetEntity: FactureAssurance::class)]
    private Collection $facturesAssurance;

    /** @var Collection<int, Transaction> */
    #[ORM\OneToMany(mappedBy: 'lotFactureAssurance', targetEntity: Transaction::class)]
    private Collection $transactions;

    public function __construct()
    {
        $this->facturesAssurance = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssurance(): ?Assurance
    {
        return $this->assurance;
    }

    public function setAssurance(?Assurance $assurance): static
    {
        $this->assurance = $assurance;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function getDateRecouvrement(): ?\DateTimeInterface
    {
        return $this->dateRecouvrement;
    }

    public function setDateRecouvrement(?\DateTimeInterface $dateRecouvrement): static
    {
        $this->dateRecouvrement = $dateRecouvrement;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return Collection<int, FactureAssurance> */
    public function getFacturesAssurance(): Collection
    {
        return $this->facturesAssurance;
    }

    public function addFactureAssurance(FactureAssurance $factureAssurance): static
    {
        if (!$this->facturesAssurance->contains($factureAssurance)) {
            $this->facturesAssurance->add($factureAssurance);
            $factureAssurance->setLotFactureAssurance($this);
        }

        return $this;
    }

    public function removeFactureAssurance(FactureAssurance $factureAssurance): static
    {
        if ($this->facturesAssurance->removeElement($factureAssurance)) {
            if ($factureAssurance->getLotFactureAssurance() === $this) {
                $factureAssurance->setLotFactureAssurance(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, Transaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function computeMontantAssureur(): float
    {
        $total = 0.0;
        foreach ($this->facturesAssurance as $facture) {
            if (!$facture instanceof FactureAssurance) {
                continue;
            }
            $totals = $facture->computeTotals();
            $total += (float) ($totals['montantAssureur'] ?? 0.0);
        }

        return $total;
    }
}

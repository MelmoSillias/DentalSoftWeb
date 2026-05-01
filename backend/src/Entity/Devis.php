<?php

namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheObservation::class, inversedBy: 'devis', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheObservation $fiche = null;

    #[ORM\ManyToOne(targetEntity: FicheMedicale::class, inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'float', nullable:true)]
    private ?float $montant = null;

    #[ORM\Column(type: 'float', nullable:true)]
    private ?float $reste = null;

    #[ORM\Column(type: 'integer', nullable:true)]
    private ?int $statut = null;

    #[ORM\OneToMany(mappedBy: 'devis', targetEntity: PaiementDevis::class, cascade: ['persist', 'remove'])]
    private Collection $paiements;

    #[ORM\OneToMany(mappedBy: 'devis', targetEntity: ContenuDevis::class, cascade: ['persist', 'remove'])]
    private Collection $contenus;

    #[ORM\Column]
    private ?int $type = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToOne(mappedBy: 'facture', cascade: ['persist', 'remove'])]
    private ?Consultation $consultation = null;

    public function __construct()
    {
        $this->contenus = new ArrayCollection();
        $this->paiements = new ArrayCollection(); // si ce n'est pas encore fait
    }

    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(PaiementDevis $paiement): self
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements[] = $paiement;
            $paiement->setDevis($this);
        }
        return $this;
    }

    public function removePaiement(PaiementDevis $paiement): self
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getDevis() === $this) {
                $paiement->setDevis(null);
            }
        }
        return $this;
    }


    public function getId(): ?int { return $this->id; }
    public function getFiche(): ?FicheObservation { return $this->fiche; }
    public function setFiche(?FicheObservation $fiche): self { $this->fiche = $fiche; return $this; }
    public function getFicheMedicale(): ?FicheMedicale { return $this->ficheMedicale; }
    public function setFicheMedicale(?FicheMedicale $ficheMedicale): self { $this->ficheMedicale = $ficheMedicale; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getMontant(): ?float { return $this->montant ?? 0.0; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }
    public function getReste(): ?float { return $this->reste ?? 0.0; }
    public function setReste(float $reste): self { $this->reste = $reste; return $this; }
    public function getStatut(): ?int { return $this->statut ?? 0; }
    public function setStatut(int $statut): self { $this->statut = $statut; return $this; }

    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenu(ContenuDevis $contenu): self
    {
        if (!$this->contenus->contains($contenu)) {
            $this->contenus[] = $contenu;
            $contenu->setDevis($this);
        }
        return $this;
    }

    public function removeContenu(ContenuDevis $contenu): self
    {
        if ($this->contenus->removeElement($contenu)) {
            if ($contenu->getDevis() === $this) {
                $contenu->setDevis(null);
            }
        }
        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

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

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        // unset the owning side of the relation if necessary
        if ($consultation === null && $this->consultation !== null) {
            $this->consultation->setFacture(null);
        }

        // set the owning side of the relation if necessary
        if ($consultation !== null && $consultation->getFacture() !== $this) {
            $consultation->setFacture($this);
        }

        $this->consultation = $consultation;

        return $this;
    }

}

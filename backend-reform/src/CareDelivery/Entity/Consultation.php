<?php

namespace App\CareDelivery\Entity;

use App\Billing\Entity\Devis;
use App\Billing\Entity\PaiementDevis;
use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Entity\FicheObservation;
use App\CareDelivery\Repository\ConsultationRepository;
use App\IdentityAccess\Entity\Employe;
use App\Patient\Entity\Patient;
use App\Scheduling\Entity\Salle;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: FicheObservation::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheObservation $fiche = null;

    #[ORM\ManyToOne(targetEntity: FicheMedicale::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy:'consultationsAsMedecin')]
    private ?Employe $medecin = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy:'consultationsAsInfirmier')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Employe $infirmier = null;

    #[ORM\ManyToOne(targetEntity: Salle::class, inversedBy: 'consultations')]
    private ?Salle $salle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $noteSeance = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'integer')]
    private int $statut = 0;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: ActeMedical::class, cascade: ['persist', 'remove'])]
    private Collection $actes;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $CreatedAt = null;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: PaiementDevis::class, cascade: ['persist', 'remove'])]
    private Collection $paiementDevis;

    #[ORM\OneToOne(inversedBy: 'consultation', cascade: ['persist', 'remove'])]
    private ?Devis $facture = null;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: Ordonnance::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ordonnances;


    public function __construct()
    {
        $this->actes = new ArrayCollection(); 
        $this->paiementDevis = new ArrayCollection();
        $this->ordonnances = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(?Patient $patient): self { $this->patient = $patient; return $this; }
    public function getFiche(): ?FicheObservation { return $this->fiche; }
    public function setFiche(?FicheObservation $fiche): self { $this->fiche = $fiche; return $this; }
    public function getFicheMedicale(): ?FicheMedicale { return $this->ficheMedicale; }
    public function setFicheMedicale(?FicheMedicale $ficheMedicale): self { $this->ficheMedicale = $ficheMedicale; return $this; }
    public function getMedecin(): ?Employe { return $this->medecin; }
    public function setMedecin(?Employe $medecin): self { $this->medecin = $medecin; return $this; }
    public function getInfirmier(): ?Employe { return $this->infirmier; }
    public function setInfirmier(?Employe $infirmier): self { $this->infirmier = $infirmier; return $this; }
    public function getSalle(): ?Salle { return $this->salle; }
    public function setSalle(?Salle $salle): self { $this->salle = $salle; return $this; }
    public function getNoteSeance(): ?string { return $this->noteSeance; }
    public function setNoteSeance(?string $noteSeance): self { $this->noteSeance = $noteSeance; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }
    public function getStatut(): int { return $this->statut; }
    public function setStatut(int $statut): self { $this->statut = $statut; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->CreatedAt; }
    public function setCreatedAt(?\DateTimeInterface $CreatedAt): self {
        $this->CreatedAt = $CreatedAt;
        return $this;
    }

    public function getActes(): Collection { return $this->actes; }
    public function addActe(ActeMedical $acte): self {
        if (!$this->actes->contains($acte)) {
            $this->actes[] = $acte;
            $acte->setConsultation($this);
        }
        return $this;
    }

    public function removeActe(ActeMedical $acte): self {
        if ($this->actes->removeElement($acte)) {
            if ($acte->getConsultation() === $this) {
                $acte->setConsultation(null);
            }
        }
        return $this;
    }
 
    public function getPaiementDevis(): ?PaiementDevis
    {
        return $this->paiementDevis->first() ?: null;
    }

    public function getPaiementsDevis(): Collection
    {
        return $this->paiementDevis;
    }

    public function setPaiementDevis(?PaiementDevis $paiementDevis): self
    {
        foreach ($this->paiementDevis as $existingPaiement) {
            $existingPaiement->setConsultation(null);
        }

        $this->paiementDevis->clear();

        if ($paiementDevis !== null) {
            $this->addPaiementDevis($paiementDevis);
        }

        return $this;
    }

    public function addPaiementDevis(PaiementDevis $paiementDevis): self
    {
        if (!$this->paiementDevis->contains($paiementDevis)) {
            $this->paiementDevis->add($paiementDevis);
            $paiementDevis->setConsultation($this);
        }

        return $this;
    }

    public function removePaiementDevis(PaiementDevis $paiementDevis): self
    {
        if ($this->paiementDevis->removeElement($paiementDevis) && $paiementDevis->getConsultation() === $this) {
            $paiementDevis->setConsultation(null);
        }

        return $this;
    }

    public function getFacture(): ?Devis
    {
        return $this->facture;
    }

    public function setFacture(?Devis $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    /** @return Collection<int, Ordonnance> */
    public function getOrdonnances(): Collection
    {
        return $this->ordonnances;
    }

    public function addOrdonnance(Ordonnance $ordonnance): self
    {
        if (!$this->ordonnances->contains($ordonnance)) {
            $this->ordonnances[] = $ordonnance;
            $ordonnance->setConsultation($this);
        }
        return $this;
    }

    public function removeOrdonnance(Ordonnance $ordonnance): self
    {
        if ($this->ordonnances->removeElement($ordonnance) && $ordonnance->getConsultation() === $this) {
            $ordonnance->setConsultation(null);
        }
        return $this;
    }
}
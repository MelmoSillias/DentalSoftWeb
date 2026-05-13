<?php

namespace App\CareDelivery\Entity;

use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\Facture;
use App\Billing\Entity\Paiement;
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

    #[ORM\OneToOne(mappedBy: 'consultation', targetEntity: Paiement::class, cascade: ['persist', 'remove'])]
    private ?Paiement $paiement = null;

    #[ORM\OneToOne(mappedBy: 'consultation', cascade: ['persist', 'remove'])]
    private ?Facture $facture = null;

    #[ORM\OneToOne(mappedBy: 'consultation', targetEntity: FactureAssurance::class, cascade: ['persist', 'remove'])]
    private ?FactureAssurance $factureAssurance = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRecouvre = false;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: Ordonnance::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ordonnances;


    public function __construct()
    {
        $this->actes = new ArrayCollection(); 
        $this->paiement = null;
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
 
    public function getPaiement(): ?Paiement
    {
        return $this->paiement;
    }

    public function setPaiement(?Paiement $paiement): self
    {
        if ($this->paiement !== null) {
            $this->paiement->setConsultation(null);
        }

        if ($paiement !== null) {
            $paiement->setConsultation($this);
        }

        $this->paiement = $paiement;

        return $this;
    }
    
    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        if ($facture === null && $this->facture !== null) {
            $this->facture->setConsultation(null);
        }

        if ($facture !== null && $facture->getConsultation() !== $this) {
            $facture->setConsultation($this);
        }

        $this->facture = $facture;

        return $this;
    }

    public function getFactureAssurance(): ?FactureAssurance
    {
        return $this->factureAssurance;
    }

    public function setFactureAssurance(?FactureAssurance $factureAssurance): static
    {
        if ($factureAssurance === null && $this->factureAssurance !== null) {
            $this->factureAssurance->setConsultation(null);
        }

        if ($factureAssurance !== null && $factureAssurance->getConsultation() !== $this) {
            $factureAssurance->setConsultation($this);
        }

        $this->factureAssurance = $factureAssurance;

        return $this;
    }

    public function isRecouvre(): bool
    {
        return $this->isRecouvre;
    }

    public function setIsRecouvre(bool $isRecouvre): static
    {
        $this->isRecouvre = $isRecouvre;

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

<?php

namespace App\Entity;

use App\Repository\FicheObservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: FicheObservationRepository::class)]
class FicheObservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'fichesObservation')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $histoireMaladie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $soinsAnterieurs = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exoInspection = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exoPalpation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $endoInspection = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $endoPalpation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $occlusion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $examenParodontal = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitementUrgence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitementDentaire = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitementParodontal = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $traitementOrthodontique = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $autres = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'fiche', targetEntity: Consultation::class)]
    private Collection $consultations;

    #[ORM\OneToMany(mappedBy: 'fiche', targetEntity: ExamenDentaire::class, cascade: ['persist', 'remove'])]
    private Collection $examensDentaires;

    #[ORM\OneToMany(mappedBy: 'fiche', targetEntity: Devis::class, cascade: ['persist', 'remove'])]
    #[ORM\OneToOne(mappedBy: 'fiche', targetEntity: Devis::class, cascade: ['persist', 'remove'])]
    private ?Devis $devis = null;

    #[ORM\OneToMany(targetEntity: DocumentMedical::class, mappedBy: 'fiche')]
    private Collection $documentsMedicaux;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->consultations = new ArrayCollection();
        $this->examensDentaires = new ArrayCollection();
        $this->documentsMedicaux = new ArrayCollection();
        $this->devis = new Devis(); 
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(?Patient $patient): self { $this->patient = $patient; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): self { $this->motif = $motif; return $this; }
    public function getHistoireMaladie(): ?string { return $this->histoireMaladie; }
    public function setHistoireMaladie(?string $histoireMaladie): self { $this->histoireMaladie = $histoireMaladie; return $this; }
    public function getSoinsAnterieurs(): ?string { return $this->soinsAnterieurs; }
    public function setSoinsAnterieurs(?string $soinsAnterieurs): self { $this->soinsAnterieurs = $soinsAnterieurs; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setExoInspection(?string $exoInspection): self
    {
        $this->exoInspection = $exoInspection;
        return $this;
    }

    public function getExoInspection(): ?string
    {
        return $this->exoInspection;
    }

    public function setExoPalpation(?string $exoPalpation): self
    {
        $this->exoPalpation = $exoPalpation;
        return $this;
    }

    public function getExoPalpation(): ?string
    {
        return $this->exoPalpation;
    }

    public function setEndoInspection(?string $endoInspection): self
    {
        $this->endoInspection = $endoInspection;
        return $this;
    }

    public function getEndoInspection(): ?string
    {
        return $this->endoInspection;
    }

    public function setEndoPalpation(?string $endoPalpation): self
    {
        $this->endoPalpation = $endoPalpation;
        return $this;
    }

    public function getEndoPalpation(): ?string
    {
        return $this->endoPalpation;
    }

    public function setOcclusion(?string $occlusion): self
    {
        $this->occlusion = $occlusion;
        return $this;
    }

    public function getOcclusion(): ?string
    {
        return $this->occlusion;
    }

    public function setExamenParodontal(?string $examenParodontal): self
    {
        $this->examenParodontal = $examenParodontal;
        return $this;
    }

    public function getExamenParodontal(): ?string
    {
        return $this->examenParodontal;
    }

    public function setDiagnostic(?string $diagnostic): self
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getDiagnostic(): ?string
    {
        return $this->diagnostic;
    }

    public function setTraitementUrgence(?string $traitementUrgence): self
    {
        $this->traitementUrgence = $traitementUrgence;
        return $this;
    }

    public function getTraitementUrgence(): ?string
    {
        return $this->traitementUrgence;
    }

    public function setTraitementDentaire(?string $traitementDentaire): self
    {
        $this->traitementDentaire = $traitementDentaire;
        return $this;
    }

    public function getTraitementDentaire(): ?string
    {
        return $this->traitementDentaire;
    }

    public function setTraitementParodontal(?string $traitementParodontal): self
    {
        $this->traitementParodontal = $traitementParodontal;
        return $this;
    }

    public function getTraitementParodontal(): ?string
    {
        return $this->traitementParodontal;
    }

    public function setTraitementOrthodontique(?string $traitementOrthodontique): self
    {
        $this->traitementOrthodontique = $traitementOrthodontique;
        return $this;
    }

    public function getTraitementOrthodontique(): ?string
    {
        return $this->traitementOrthodontique;
    }

    public function setAutres(?string $autres): self
    {
        $this->autres = $autres;
        return $this;
    }

    public function getAutres(): ?string
    {
        return $this->autres;
    }


    public function getConsultations(): Collection { return $this->consultations; }
    public function addConsultation(Consultation $consultation): self {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations[] = $consultation;
            $consultation->setFiche($this);
        }
        return $this;
    }

    public function getExamensDentaires(): Collection { return $this->examensDentaires; }
    public function addExamenDentaire(ExamenDentaire $examen): self {
        if (!$this->examensDentaires->contains($examen)) {
            $this->examensDentaires[] = $examen;
            $examen->setFiche($this);
        }
        return $this;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): self
    {
        $this->devis = $devis;

        // Set the owning side of the relation if necessary
        if ($devis !== null && $devis->getFiche() !== $this) {
            $devis->setFiche($this);
        }

        return $this;
    }

    public function getDocumentsMedicaux(): Collection
    {
        return $this->documentsMedicaux;
    } 

    public function addDocumentMedical(DocumentMedical $documentMedical): static
    {
        if (!$this->documentsMedicaux->contains($documentMedical)) {
            $this->documentsMedicaux[] = $documentMedical;
            $documentMedical->setFiche($this);
        }

        return $this;
    }
    public function removeDocumentMedical(DocumentMedical $documentMedical): static
    {
        if ($this->documentsMedicaux->removeElement($documentMedical)) {
            // set the owning side to null (unless already changed)
            if ($documentMedical->getFiche() === $this) {
                $documentMedical->setFiche(null);
            }
        }

        return $this;
    } 
    
}

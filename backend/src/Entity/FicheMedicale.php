<?php

namespace App\Entity;

use App\Repository\FicheMedicaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheMedicaleRepository::class)]
class FicheMedicale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'fichesMedicales')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Employe $medecin = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToOne(mappedBy: 'ficheMedicale', targetEntity: FicheEntretien::class, cascade: ['persist', 'remove'])]
    private ?FicheEntretien $entretien = null;

    #[ORM\OneToOne(mappedBy: 'ficheMedicale', targetEntity: FicheExamen::class, cascade: ['persist', 'remove'])]
    private ?FicheExamen $examen = null;

    #[ORM\OneToOne(mappedBy: 'ficheMedicale', targetEntity: FicheBilan::class, cascade: ['persist', 'remove'])]
    private ?FicheBilan $bilan = null;

    #[ORM\OneToMany(mappedBy: 'ficheMedicale', targetEntity: FichePlanTraitement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $plansTraitement;

    #[ORM\OneToMany(mappedBy: 'ficheMedicale', targetEntity: FicheDocument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'ficheMedicale', targetEntity: Devis::class, cascade: ['persist', 'remove'])]
    private Collection $devis;

    #[ORM\OneToMany(mappedBy: 'ficheMedicale', targetEntity: Consultation::class)]
    private Collection $consultations;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->plansTraitement = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->devis = new ArrayCollection();
        $this->consultations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    public function getMedecin(): ?Employe
    {
        return $this->medecin;
    }

    public function setMedecin(?Employe $medecin): static
    {
        $this->medecin = $medecin;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getEntretien(): ?FicheEntretien
    {
        return $this->entretien;
    }

    public function setEntretien(?FicheEntretien $entretien): static
    {
        $this->entretien = $entretien;

        if ($entretien && $entretien->getFicheMedicale() !== $this) {
            $entretien->setFicheMedicale($this);
        }

        return $this;
    }

    public function getExamen(): ?FicheExamen
    {
        return $this->examen;
    }

    public function setExamen(?FicheExamen $examen): static
    {
        $this->examen = $examen;

        if ($examen && $examen->getFicheMedicale() !== $this) {
            $examen->setFicheMedicale($this);
        }

        return $this;
    }

    public function getBilan(): ?FicheBilan
    {
        return $this->bilan;
    }

    public function setBilan(?FicheBilan $bilan): static
    {
        $this->bilan = $bilan;

        if ($bilan && $bilan->getFicheMedicale() !== $this) {
            $bilan->setFicheMedicale($this);
        }

        return $this;
    }

    public function getPlansTraitement(): Collection
    {
        return $this->plansTraitement;
    }

    public function addPlanTraitement(FichePlanTraitement $plan): static
    {
        if (!$this->plansTraitement->contains($plan)) {
            $this->plansTraitement[] = $plan;
            $plan->setFicheMedicale($this);
        }

        return $this;
    }

    public function removePlanTraitement(FichePlanTraitement $plan): static
    {
        if ($this->plansTraitement->removeElement($plan)) {
            if ($plan->getFicheMedicale() === $this) {
                $plan->setFicheMedicale(null);
            }
        }

        return $this;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(FicheDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents[] = $document;
            $document->setFicheMedicale($this);
        }

        return $this;
    }

    public function removeDocument(FicheDocument $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getFicheMedicale() === $this) {
                $document->setFicheMedicale(null);
            }
        }

        return $this;
    }

    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevis(Devis $devis): static
    {
        if (!$this->devis->contains($devis)) {
            $this->devis[] = $devis;
            $devis->setFicheMedicale($this);
        }

        return $this;
    }

    public function removeDevis(Devis $devis): static
    {
        if ($this->devis->removeElement($devis)) {
            if ($devis->getFicheMedicale() === $this) {
                $devis->setFicheMedicale(null);
            }
        }

        return $this;
    }

    public function getConsultations(): Collection
    {
        return $this->consultations;
    }

    public function addConsultation(Consultation $consultation): static
    {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations[] = $consultation;
            $consultation->setFicheMedicale($this);
        }

        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation)) {
            if ($consultation->getFicheMedicale() === $this) {
                $consultation->setFicheMedicale(null);
            }
        }

        return $this;
    }
}

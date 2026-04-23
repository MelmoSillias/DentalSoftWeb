<?php

namespace App\ClinicalRecord\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheEntretien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'entretien', targetEntity: FicheMedicale::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifConsultation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anamnese = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $allaitement = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $grossesseEnCours = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $menstrues = null;

    #[ORM\OneToMany(mappedBy: 'entretien', targetEntity: FicheEntretienMedicament::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $medicaments;

    #[ORM\OneToMany(mappedBy: 'entretien', targetEntity: FicheEntretienAffection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $affections;

    #[ORM\OneToMany(mappedBy: 'entretien', targetEntity: FicheEntretienQuestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'entretien', targetEntity: FicheEntretienHabitude::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $habitudes;

    public function __construct()
    {
        $this->medicaments = new ArrayCollection();
        $this->affections = new ArrayCollection();
        $this->questions = new ArrayCollection();
        $this->habitudes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFicheMedicale(): ?FicheMedicale
    {
        return $this->ficheMedicale;
    }

    public function setFicheMedicale(?FicheMedicale $ficheMedicale): static
    {
        $this->ficheMedicale = $ficheMedicale;
        return $this;
    }

    public function getMotifConsultation(): ?string
    {
        return $this->motifConsultation;
    }

    public function setMotifConsultation(?string $motifConsultation): static
    {
        $this->motifConsultation = $motifConsultation;
        return $this;
    }

    public function getAnamnese(): ?string
    {
        return $this->anamnese;
    }

    public function setAnamnese(?string $anamnese): static
    {
        $this->anamnese = $anamnese;
        return $this;
    }

    public function getAllaitement(): ?bool
    {
        return $this->allaitement;
    }

    public function setAllaitement(?bool $allaitement): static
    {
        $this->allaitement = $allaitement;
        return $this;
    }

    public function getGrossesseEnCours(): ?bool
    {
        return $this->grossesseEnCours;
    }

    public function setGrossesseEnCours(?bool $grossesseEnCours): static
    {
        $this->grossesseEnCours = $grossesseEnCours;
        return $this;
    }

    public function getMenstrues(): ?bool
    {
        return $this->menstrues;
    }

    public function setMenstrues(?bool $menstrues): static
    {
        $this->menstrues = $menstrues;
        return $this;
    }

    public function getMedicaments(): Collection
    {
        return $this->medicaments;
    }

    public function addMedicament(FicheEntretienMedicament $medicament): static
    {
        if (!$this->medicaments->contains($medicament)) {
            $this->medicaments[] = $medicament;
            $medicament->setEntretien($this);
        }

        return $this;
    }

    public function removeMedicament(FicheEntretienMedicament $medicament): static
    {
        if ($this->medicaments->removeElement($medicament)) {
            if ($medicament->getEntretien() === $this) {
                $medicament->setEntretien(null);
            }
        }

        return $this;
    }

    public function getAffections(): Collection
    {
        return $this->affections;
    }

    public function addAffection(FicheEntretienAffection $affection): static
    {
        if (!$this->affections->contains($affection)) {
            $this->affections[] = $affection;
            $affection->setEntretien($this);
        }

        return $this;
    }

    public function removeAffection(FicheEntretienAffection $affection): static
    {
        if ($this->affections->removeElement($affection)) {
            if ($affection->getEntretien() === $this) {
                $affection->setEntretien(null);
            }
        }

        return $this;
    }

    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(FicheEntretienQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions[] = $question;
            $question->setEntretien($this);
        }

        return $this;
    }

    public function removeQuestion(FicheEntretienQuestion $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getEntretien() === $this) {
                $question->setEntretien(null);
            }
        }

        return $this;
    }

    public function getHabitudes(): Collection
    {
        return $this->habitudes;
    }

    public function addHabitude(FicheEntretienHabitude $habitude): static
    {
        if (!$this->habitudes->contains($habitude)) {
            $this->habitudes[] = $habitude;
            $habitude->setEntretien($this);
        }

        return $this;
    }

    public function removeHabitude(FicheEntretienHabitude $habitude): static
    {
        if ($this->habitudes->removeElement($habitude)) {
            if ($habitude->getEntretien() === $this) {
                $habitude->setEntretien(null);
            }
        }

        return $this;
    }
}

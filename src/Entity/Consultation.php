<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
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

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy:'consultationsAsMedecin')]
    private ?Employe $medecin = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy:'consultationsAsInfirmier')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Employe $infirmier = null;

    #[ORM\ManyToOne(targetEntity: Salle::class, inversedBy: 'consultations')]
    private ?Salle $salle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $noteSeance = null;

    #[ORM\Column(type: 'integer')]
    private int $statut = 0;

    #[ORM\OneToMany(mappedBy: 'consultation', targetEntity: ActeMedical::class, cascade: ['persist', 'remove'])]
    private Collection $actes;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $CreatedAt = null;

    public function __construct()
    {
        $this->actes = new ArrayCollection(); 
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(?Patient $patient): self { $this->patient = $patient; return $this; }
    public function getFiche(): ?FicheObservation { return $this->fiche; }
    public function setFiche(?FicheObservation $fiche): self { $this->fiche = $fiche; return $this; }
    public function getMedecin(): ?Employe { return $this->medecin; }
    public function setMedecin(?Employe $medecin): self { $this->medecin = $medecin; return $this; }
    public function getInfirmier(): ?Employe { return $this->infirmier; }
    public function setInfirmier(?Employe $infirmier): self { $this->infirmier = $infirmier; return $this; }
    public function getSalle(): ?Salle { return $this->salle; }
    public function setSalle(?Salle $salle): self { $this->salle = $salle; return $this; }
    public function getNoteSeance(): ?string { return $this->noteSeance; }
    public function setNoteSeance(?string $noteSeance): self { $this->noteSeance = $noteSeance; return $this; }
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

}

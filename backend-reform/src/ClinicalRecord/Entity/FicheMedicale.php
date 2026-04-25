<?php

namespace App\ClinicalRecord\Entity;

use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\CareDelivery\Entity\Consultation;
use App\IdentityAccess\Entity\Employe;
use App\Patient\Entity\Patient;
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

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $formTemplateKey = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $formData = [];

    #[ORM\OneToMany(mappedBy: 'ficheMedicale', targetEntity: Consultation::class)]
    private Collection $consultations;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getFormTemplateKey(): ?string
    {
        return $this->formTemplateKey;
    }

    public function setFormTemplateKey(?string $formTemplateKey): static
    {
        $this->formTemplateKey = $formTemplateKey;
        return $this;
    }

    public function getFormData(): ?array
    {
        return $this->formData;
    }

    public function setFormData(?array $formData): static
    {
        $this->formData = $formData;
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
            /** @var \App\ClinicalRecord\Entity\FicheMedicale $currentFicheMedicale */
            $currentFicheMedicale = $this;
            $consultation->setFicheMedicale($currentFicheMedicale);
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

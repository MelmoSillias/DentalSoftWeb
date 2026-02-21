<?php

namespace App\Entity;

use App\Repository\DocumentMedicalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentMedicalRepository::class)]
class DocumentMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fichier = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDossier = null;

    #[ORM\ManyToOne(inversedBy: 'documentsMedicaux')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheObservation $fiche = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }
    public function setLibelle(string $l): self
    {
        $this->libelle = $l;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $d): self
    {
        $this->description = $d;
        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }
    public function setFichier(string $f): self
    {
        $this->fichier = $f;
        return $this;
    }

    public function getDateDossier(): ?\DateTimeInterface
    {
        return $this->dateDossier;
    }
    public function setDateDossier(\DateTimeInterface $d): self
    {
        $this->dateDossier = $d;
        return $this;
    }

    public function getFiche(): ?FicheObservation
    {
        return $this->fiche;
    }

    public function setFiche(?FicheObservation $fiche): static
    {
        $this->fiche = $fiche;

        return $this;
    }

}

<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheExamen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'examen', targetEntity: FicheMedicale::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $occlusion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mediane = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $classesAngle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vestibules = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hbd = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $brossage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $soccu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cinematiqueMandibulaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ouvertureBuccale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $temperatureBuccale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $amplitudeOuverture = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bruitsArticulaires = null;

    #[ORM\Column(type: Types::JSON)]
    private array $tissusMousTable = [];

    #[ORM\Column(type: Types::JSON)]
    private array $tissusDursTable = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $examenCanauxExcreteurs = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnosticSupposeExamens = null;

    #[ORM\OneToMany(mappedBy: 'examen', targetEntity: FicheExamenItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToMany(mappedBy: 'examen', targetEntity: FicheExamenLabo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $examensLabo;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->examensLabo = new ArrayCollection();
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

    public function getOcclusion(): ?string
    {
        return $this->occlusion;
    }

    public function setOcclusion(?string $occlusion): static
    {
        $this->occlusion = $occlusion;
        return $this;
    }

    public function getMediane(): ?string
    {
        return $this->mediane;
    }

    public function setMediane(?string $mediane): static
    {
        $this->mediane = $mediane;
        return $this;
    }

    public function getClassesAngle(): ?string
    {
        return $this->classesAngle;
    }

    public function setClassesAngle(?string $classesAngle): static
    {
        $this->classesAngle = $classesAngle;
        return $this;
    }

    public function getVestibules(): ?string
    {
        return $this->vestibules;
    }

    public function setVestibules(?string $vestibules): static
    {
        $this->vestibules = $vestibules;
        return $this;
    }

    public function getHbd(): ?string
    {
        return $this->hbd;
    }

    public function setHbd(?string $hbd): static
    {
        $this->hbd = $hbd;
        return $this;
    }

    public function getBrossage(): ?string
    {
        return $this->brossage;
    }

    public function setBrossage(?string $brossage): static
    {
        $this->brossage = $brossage;
        return $this;
    }

    public function getSoccu(): ?string
    {
        return $this->soccu;
    }

    public function setSoccu(?string $soccu): static
    {
        $this->soccu = $soccu;
        return $this;
    }

    public function getCinematiqueMandibulaire(): ?string
    {
        return $this->cinematiqueMandibulaire;
    }

    public function setCinematiqueMandibulaire(?string $cinematiqueMandibulaire): static
    {
        $this->cinematiqueMandibulaire = $cinematiqueMandibulaire;
        return $this;
    }

    public function getOuvertureBuccale(): ?string
    {
        return $this->ouvertureBuccale;
    }

    public function setOuvertureBuccale(?string $ouvertureBuccale): static
    {
        $this->ouvertureBuccale = $ouvertureBuccale;
        return $this;
    }

    public function getTemperatureBuccale(): ?string
    {
        return $this->temperatureBuccale;
    }

    public function setTemperatureBuccale(?string $temperatureBuccale): static
    {
        $this->temperatureBuccale = $temperatureBuccale;
        return $this;
    }

    public function getAmplitudeOuverture(): ?string
    {
        return $this->amplitudeOuverture;
    }

    public function setAmplitudeOuverture(?string $amplitudeOuverture): static
    {
        $this->amplitudeOuverture = $amplitudeOuverture;
        return $this;
    }

    public function getBruitsArticulaires(): ?string
    {
        return $this->bruitsArticulaires;
    }

    public function setBruitsArticulaires(?string $bruitsArticulaires): static
    {
        $this->bruitsArticulaires = $bruitsArticulaires;
        return $this;
    }

    public function getTissusMousTable(): array
    {
        return $this->tissusMousTable;
    }

    public function setTissusMousTable(array $tissusMousTable): static
    {
        $this->tissusMousTable = $tissusMousTable;
        return $this;
    }

    public function getTissusDursTable(): array
    {
        return $this->tissusDursTable;
    }

    public function setTissusDursTable(array $tissusDursTable): static
    {
        $this->tissusDursTable = $tissusDursTable;
        return $this;
    }

    public function getExamenCanauxExcreteurs(): ?string
    {
        return $this->examenCanauxExcreteurs;
    }

    public function setExamenCanauxExcreteurs(?string $examenCanauxExcreteurs): static
    {
        $this->examenCanauxExcreteurs = $examenCanauxExcreteurs;
        return $this;
    }

    public function getDiagnosticSupposeExamens(): ?string
    {
        return $this->diagnosticSupposeExamens;
    }

    public function setDiagnosticSupposeExamens(?string $diagnosticSupposeExamens): static
    {
        $this->diagnosticSupposeExamens = $diagnosticSupposeExamens;
        return $this;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(FicheExamenItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setExamen($this);
        }

        return $this;
    }

    public function removeItem(FicheExamenItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getExamen() === $this) {
                $item->setExamen(null);
            }
        }

        return $this;
    }

    public function getExamensLabo(): Collection
    {
        return $this->examensLabo;
    }

    public function addExamenLabo(FicheExamenLabo $examenLabo): static
    {
        if (!$this->examensLabo->contains($examenLabo)) {
            $this->examensLabo[] = $examenLabo;
            $examenLabo->setExamen($this);
        }

        return $this;
    }

    public function removeExamenLabo(FicheExamenLabo $examenLabo): static
    {
        if ($this->examensLabo->removeElement($examenLabo)) {
            if ($examenLabo->getExamen() === $this) {
                $examenLabo->setExamen(null);
            }
        }

        return $this;
    }
}

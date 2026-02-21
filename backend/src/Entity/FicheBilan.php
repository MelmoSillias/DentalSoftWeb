<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheBilan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'bilan', targetEntity: FicheMedicale::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(type: Types::JSON)]
    private array $formuleDentaire = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $radiographieExtraBuccaleHypothese = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $radiographieIntraBuccaleHypothese = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nfsDetaillee = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tpTcaInr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $uree = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $creatininemie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $glycemie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnosticPositif = null;

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

    public function getFormuleDentaire(): array
    {
        return $this->formuleDentaire;
    }

    public function setFormuleDentaire(array $formuleDentaire): static
    {
        $this->formuleDentaire = $formuleDentaire;
        return $this;
    }

    public function getRadiographieExtraBuccaleHypothese(): ?string
    {
        return $this->radiographieExtraBuccaleHypothese;
    }

    public function setRadiographieExtraBuccaleHypothese(?string $radiographieExtraBuccaleHypothese): static
    {
        $this->radiographieExtraBuccaleHypothese = $radiographieExtraBuccaleHypothese;
        return $this;
    }

    public function getRadiographieIntraBuccaleHypothese(): ?string
    {
        return $this->radiographieIntraBuccaleHypothese;
    }

    public function setRadiographieIntraBuccaleHypothese(?string $radiographieIntraBuccaleHypothese): static
    {
        $this->radiographieIntraBuccaleHypothese = $radiographieIntraBuccaleHypothese;
        return $this;
    }

    public function getNfsDetaillee(): ?string
    {
        return $this->nfsDetaillee;
    }

    public function setNfsDetaillee(?string $nfsDetaillee): static
    {
        $this->nfsDetaillee = $nfsDetaillee;
        return $this;
    }

    public function getTpTcaInr(): ?string
    {
        return $this->tpTcaInr;
    }

    public function setTpTcaInr(?string $tpTcaInr): static
    {
        $this->tpTcaInr = $tpTcaInr;
        return $this;
    }

    public function getUree(): ?string
    {
        return $this->uree;
    }

    public function setUree(?string $uree): static
    {
        $this->uree = $uree;
        return $this;
    }

    public function getCreatininemie(): ?string
    {
        return $this->creatininemie;
    }

    public function setCreatininemie(?string $creatininemie): static
    {
        $this->creatininemie = $creatininemie;
        return $this;
    }

    public function getGlycemie(): ?string
    {
        return $this->glycemie;
    }

    public function setGlycemie(?string $glycemie): static
    {
        $this->glycemie = $glycemie;
        return $this;
    }

    public function getDiagnosticPositif(): ?string
    {
        return $this->diagnosticPositif;
    }

    public function setDiagnosticPositif(?string $diagnosticPositif): static
    {
        $this->diagnosticPositif = $diagnosticPositif;
        return $this;
    }
}

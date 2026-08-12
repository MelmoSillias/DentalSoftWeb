<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FichePlanTraitement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'plansTraitement')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $planIndex = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateSupposed = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

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

    public function getPlanIndex(): ?int
    {
        return $this->planIndex;
    }

    public function setPlanIndex(?int $planIndex): static
    {
        $this->planIndex = $planIndex;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDateSupposed(): ?\DateTimeInterface
    {
        return $this->dateSupposed;
    }

    public function setDateSupposed(?\DateTimeInterface $dateSupposed): static
    {
        $this->dateSupposed = $dateSupposed;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }
}

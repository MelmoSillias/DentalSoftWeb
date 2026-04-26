<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheExamenLabo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'examensLabo')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheExamen $examen = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $dateExamen = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExamen(): ?FicheExamen
    {
        return $this->examen;
    }

    public function setExamen(?FicheExamen $examen): static
    {
        $this->examen = $examen;
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

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;
        return $this;
    }

    public function getResultat(): ?string
    {
        return $this->resultat;
    }

    public function setResultat(?string $resultat): static
    {
        $this->resultat = $resultat;
        return $this;
    }

    public function getDateExamen(): ?DateTimeInterface
    {
        return $this->dateExamen;
    }

    public function setDateExamen(?DateTimeInterface $dateExamen): static
    {
        $this->dateExamen = $dateExamen;
        return $this;
    }
}

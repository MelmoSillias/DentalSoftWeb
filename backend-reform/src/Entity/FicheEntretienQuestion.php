<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FicheEntretienQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheEntretien $entretien = null;

    #[ORM\Column(length: 255)]
    private ?string $question = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $reponse = null;

    #[ORM\Column(name: '`precision`', type: 'text', nullable: true)]
    private ?string $precision = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntretien(): ?FicheEntretien
    {
        return $this->entretien;
    }

    public function setEntretien(?FicheEntretien $entretien): static
    {
        $this->entretien = $entretien;
        return $this;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getReponse(): ?bool
    {
        return $this->reponse;
    }

    public function setReponse(?bool $reponse): static
    {
        $this->reponse = $reponse;
        return $this;
    }

    public function getPrecision(): ?string
    {
        return $this->precision;
    }

    public function setPrecision(?string $precision): static
    {
        $this->precision = $precision;
        return $this;
    }
}

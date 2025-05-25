<?php

namespace App\Entity;

use App\Repository\ActeMedicalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActeMedicalRepository::class)]
class ActeMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class, inversedBy: 'actes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consultation $consultation = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[ORM\JoinColumn(nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $dent = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'float')]
    private ?float $prix = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }
    public function setConsultation(?Consultation $c): self
    {
        $this->consultation = $c;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(?string $t): self
    {
        $this->type = $t;
        return $this;
    }

    public function getDent(): ?string
    {
        return $this->dent;
    }
    public function setDent(string $d): self
    {
        $this->dent = $d;
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

    public function getPrix(): ?float
    {
        return $this->prix;
    }
    public function setPrix(float $p): self
    {
        $this->prix = $p;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }
    public function setQuantite(int $q): self
    {
        $this->quantite = $q;
        return $this;
    }
}

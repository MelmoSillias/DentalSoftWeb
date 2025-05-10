<?php

namespace App\Entity;

use App\Repository\ContactUrgenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactUrgenceRepository::class)]
class ContactUrgence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'contactsUrgence')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $lienParente = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $telephone = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }
    public function setPatient(?Patient $p): self
    {
        $this->patient = $p;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(string $n): self
    {
        $this->nom = $n;
        return $this;
    }

    public function getLienParente(): ?string
    {
        return $this->lienParente;
    }
    public function setLienParente(string $l): self
    {
        $this->lienParente = $l;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }
    public function setTelephone(string $t): self
    {
        $this->telephone = $t;
        return $this;
    }
}

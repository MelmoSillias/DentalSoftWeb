<?php

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockRepository::class)]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consommable $consommable = null;

    #[ORM\Column]
    private ?int $quantiteUtilisee = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePrise = null;

    #[ORM\ManyToOne(inversedBy: 'stocks')]  
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $employee = null;


    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;
    

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsommable(): ?Consommable
    {
        return $this->consommable;
    }

    public function setConsommable(?Consommable $consommable): static
    {
        $this->consommable = $consommable;

        return $this;
    }

    public function getQuantiteUtilisee(): ?int
    {
        return $this->quantiteUtilisee;
    }

    public function setQuantiteUtilisee(int $quantiteUtilisee): static
    {
        $this->quantiteUtilisee = $quantiteUtilisee;

        return $this;
    }

    public function getDatePrise(): ?\DateTimeInterface
    {
        return $this->datePrise;
    }

    public function setDatePrise(\DateTimeInterface $datePrise): static
    {
        $this->datePrise = $datePrise;

        return $this;
    }

    public function getEmployee(): ?Employe
    {
        return $this->employee;
    }

    public function setEmployee(?Employe $employee): self
    {
        $this->employee = $employee;

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\ExamenDentaireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenDentaireRepository::class)]
class ExamenDentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheObservation::class, inversedBy: 'examensDentaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FicheObservation $fiche = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $designation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultat = null;

    public function getId(): ?int { return $this->id; }
    public function getFiche(): ?FicheObservation { return $this->fiche; }
    public function setFiche(?FicheObservation $fiche): self { $this->fiche = $fiche; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getDesignation(): ?string { return $this->designation; }
    public function setDesignation(string $designation): self { $this->designation = $designation; return $this; }
    public function getResultat(): ?string { return $this->resultat; }
    public function setResultat(?string $resultat): self { $this->resultat = $resultat; return $this; }
}

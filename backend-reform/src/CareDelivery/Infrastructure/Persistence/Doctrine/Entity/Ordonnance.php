<?php

namespace App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\OrdonnanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: OrdonnanceRepository::class)]
class Ordonnance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class, inversedBy: 'ordonnances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consultation $consultation = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $medecinNom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\OneToMany(mappedBy: 'ordonnance', targetEntity: OrdonnanceLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->date = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): self
    {
        $this->consultation = $consultation;
        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getMedecinNom(): ?string
    {
        return $this->medecinNom;
    }

    public function setMedecinNom(?string $medecinNom): self
    {
        $this->medecinNom = $medecinNom;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    /** @return Collection<int, OrdonnanceLigne> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(OrdonnanceLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes[] = $ligne;
            $ligne->setOrdonnance($this);
        }
        return $this;
    }

    public function removeLigne(OrdonnanceLigne $ligne): self
    {
        if ($this->lignes->removeElement($ligne) && $ligne->getOrdonnance() === $this) {
            $ligne->setOrdonnance(null);
        }
        return $this;
    }
}

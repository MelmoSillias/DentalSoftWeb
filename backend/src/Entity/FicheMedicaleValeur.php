<?php

namespace App\Entity;

use App\Repository\FicheMedicaleValeurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheMedicaleValeurRepository::class)]
#[ORM\Table(name: 'fiche_medicale_valeur')]
#[ORM\UniqueConstraint(name: 'uniq_fiche_medicale_valeur_champ', fields: ['ficheMedicale', 'champ'])]
#[ORM\HasLifecycleCallbacks]
class FicheMedicaleValeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheMedicale::class, inversedBy: 'formValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\ManyToOne(targetEntity: Champ::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Champ $champ = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $value = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getChamp(): ?Champ
    {
        return $this->champ;
    }

    public function setChamp(?Champ $champ): static
    {
        $this->champ = $champ;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
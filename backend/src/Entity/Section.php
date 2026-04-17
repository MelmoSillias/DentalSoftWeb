<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'section')]
#[ORM\UniqueConstraint(name: 'uniq_section_onglet_code', fields: ['onglet', 'code'])]
class Section
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Onglet::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Onglet $onglet = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 50)]
    private string $type = 'component';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $componentKey = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $configuration = [];

    #[ORM\Column(type: Types::JSON)]
    private array $conditions = [];

    #[ORM\OneToMany(mappedBy: 'section', targetEntity: Champ::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $champs;

    public function __construct()
    {
        $this->champs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOnglet(): ?Onglet
    {
        return $this->onglet;
    }

    public function setOnglet(?Onglet $onglet): static
    {
        $this->onglet = $onglet;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getComponentKey(): ?string
    {
        return $this->componentKey;
    }

    public function setComponentKey(?string $componentKey): static
    {
        $this->componentKey = $componentKey;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): static
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getChamps(): Collection
    {
        return $this->champs;
    }

    public function addChamp(Champ $champ): static
    {
        if (!$this->champs->contains($champ)) {
            $this->champs->add($champ);
            $champ->setSection($this);
        }

        return $this;
    }

    public function removeChamp(Champ $champ): static
    {
        if ($this->champs->removeElement($champ) && $champ->getSection() === $this) {
            $champ->setSection(null);
        }

        return $this;
    }
}
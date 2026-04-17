<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'champ')]
#[ORM\UniqueConstraint(name: 'uniq_champ_section_code', fields: ['section', 'code'])]
class Champ
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Section::class, inversedBy: 'champs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Section $section = null;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 80)]
    private string $fieldType = 'json';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $rendererKey = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private bool $isRepeated = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $defaultValue = null;

    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column(type: Types::JSON)]
    private array $validationRules = [];

    #[ORM\Column(type: Types::JSON)]
    private array $conditions = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): static
    {
        $this->section = $section;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    public function setFieldType(string $fieldType): static
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getRendererKey(): ?string
    {
        return $this->rendererKey;
    }

    public function setRendererKey(?string $rendererKey): static
    {
        $this->rendererKey = $rendererKey;

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

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    public function isRepeated(): bool
    {
        return $this->isRepeated;
    }

    public function setIsRepeated(bool $isRepeated): static
    {
        $this->isRepeated = $isRepeated;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function setValidationRules(array $validationRules): static
    {
        $this->validationRules = $validationRules;

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
}
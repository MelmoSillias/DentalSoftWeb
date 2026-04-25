<?php

namespace App\ClinicalRecord\Entity;

use App\ClinicalRecord\Repository\FormTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormTemplateRepository::class)]
#[ORM\Table(name: 'form_template')]
class FormTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'template_key', type: Types::STRING, length: 64)]
    private ?string $key = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::JSON)]
    private array $structure = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getStructure(): array
    {
        return $this->structure;
    }

    public function setStructure(array $structure): static
    {
        $this->structure = $structure;
        return $this;
    }
}

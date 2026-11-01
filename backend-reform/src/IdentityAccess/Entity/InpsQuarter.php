<?php

namespace App\IdentityAccess\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InpsQuarter
{
    // Pre-created by migration: a stable row to lock even before the first payroll.
    #[ORM\Id]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(options: ['default' => false])]
    private bool $closed = false;

    #[ORM\Column(type: 'json')]
    private array $audit = [];
    public function record(array $entry): void { $this->audit[] = $entry; }
    public function getAudit(): array { return $this->audit; }

    public function __construct(int $id) { $this->id = $id; }
    public function getId(): int { return $this->id; }
    public function isClosed(): bool { return $this->closed; }
    public function setClosed(bool $closed): void { $this->closed = $closed; }
}

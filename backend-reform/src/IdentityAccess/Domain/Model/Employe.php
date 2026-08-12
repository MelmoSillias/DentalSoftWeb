<?php

namespace App\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\ValueObject\EmployeId;

/**
 * Minimal RH aggregate stub for Pure DDD cutover.
 * Persistence still uses legacy App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe.
 */
final class Employe
{
    private function __construct(
        private ?EmployeId $id,
        private string $nom,
        private string $prenom,
        private string $fonction,
        private string $type,
    ) {
        $this->nom = self::assertNonEmpty($nom, 'Nom');
        $this->prenom = self::assertNonEmpty($prenom, 'Prénom');
        $this->fonction = self::assertNonEmpty($fonction, 'Fonction');
        $this->type = self::assertNonEmpty($type, 'Type');
    }

    public static function reconstitute(
        EmployeId $id,
        string $nom,
        string $prenom,
        string $fonction,
        string $type,
    ): self {
        return new self($id, $nom, $prenom, $fonction, $type !== '' ? $type : 'Staff');
    }

    public static function create(string $nom, string $prenom, string $fonction, string $type): self
    {
        return new self(null, $nom, $prenom, $fonction, $type);
    }

    public function rename(string $nom, string $prenom): void
    {
        $this->nom = self::assertNonEmpty($nom, 'Nom');
        $this->prenom = self::assertNonEmpty($prenom, 'Prénom');
    }

    public function assignId(EmployeId $id): void
    {
        if ($this->id !== null) {
            throw new IdentityAccessDomainException('Employe already has an id.');
        }
        $this->id = $id;
    }

    public function getId(): ?EmployeId
    {
        return $this->id;
    }

    public function requireId(): EmployeId
    {
        if ($this->id === null) {
            throw new IdentityAccessDomainException('Employe id is not assigned.');
        }

        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getFonction(): string
    {
        return $this->fonction;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    private static function assertNonEmpty(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new IdentityAccessDomainException(sprintf('%s is required.', $label));
        }

        return $value;
    }
}

<?php

namespace App\Inventory\Domain\Model;

use App\Inventory\Domain\Exception\InventoryDomainException;
use App\Inventory\Domain\ValueObject\ConsommableId;

final class Consommable
{
    private function __construct(
        private ?ConsommableId $id,
        private string $nom,
        private int $quantity,
        private int $lowValue,
    ) {
        if (trim($this->nom) === '') {
            throw new InventoryDomainException('Consommable name is required.');
        }
        if ($this->quantity < 0 || $this->lowValue < 0) {
            throw new InventoryDomainException('Quantity and lowValue must be non-negative.');
        }
    }

    public static function reconstitute(ConsommableId $id, string $nom, int $quantity, int $lowValue): self
    {
        return new self($id, $nom, $quantity, $lowValue);
    }

    public function withdraw(int $amount): void
    {
        if ($amount <= 0) {
            throw new InventoryDomainException('Withdraw amount must be positive.');
        }
        if ($amount > $this->quantity) {
            throw new InventoryDomainException('Insufficient stock.');
        }
        $this->quantity -= $amount;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->lowValue;
    }

    public function getId(): ?ConsommableId
    {
        return $this->id;
    }

    public function requireId(): ConsommableId
    {
        if ($this->id === null) {
            throw new InventoryDomainException('Consommable id is not assigned.');
        }

        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLowValue(): int
    {
        return $this->lowValue;
    }
}

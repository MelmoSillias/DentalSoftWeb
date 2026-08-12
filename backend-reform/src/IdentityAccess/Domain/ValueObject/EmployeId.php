<?php

namespace App\IdentityAccess\Domain\ValueObject;

use InvalidArgumentException;

final class EmployeId
{
    private function __construct(private readonly int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('EmployeId must be positive.');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

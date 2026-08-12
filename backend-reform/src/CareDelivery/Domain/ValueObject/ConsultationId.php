<?php

namespace App\CareDelivery\Domain\ValueObject;

use InvalidArgumentException;

final class ConsultationId
{
    private function __construct(private readonly int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('ConsultationId must be positive.');
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

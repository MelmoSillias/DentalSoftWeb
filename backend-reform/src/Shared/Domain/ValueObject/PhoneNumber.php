<?php

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

final class PhoneNumber
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($normalized === '' || strlen($normalized) < 6 || strlen($normalized) > 55) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        if (!preg_match('/^\+?[0-9().\-]+$/', $normalized)) {
            throw new InvalidArgumentException('Invalid phone number format.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

<?php

namespace App\Settings\Domain\ValueObject;

use InvalidArgumentException;

final class SettingKey
{
    private function __construct(private readonly string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SettingKey cannot be empty.');
        }
        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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

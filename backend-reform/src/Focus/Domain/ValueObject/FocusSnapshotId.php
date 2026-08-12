<?php

namespace App\Focus\Domain\ValueObject;

use InvalidArgumentException;

final class FocusSnapshotId
{
    private function __construct(private readonly string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('FocusSnapshotId must not be empty.');
        }
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

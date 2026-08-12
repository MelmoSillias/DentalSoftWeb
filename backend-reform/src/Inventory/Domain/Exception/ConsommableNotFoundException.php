<?php

namespace App\Inventory\Domain\Exception;

use RuntimeException;

final class ConsommableNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Consommable #%d not found.', $id));
    }
}

<?php

namespace App\Billing\Domain\Exception;

use RuntimeException;

final class DevisNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Devis #%d not found.', $id));
    }
}

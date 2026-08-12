<?php

namespace App\Billing\Domain\Exception;

use RuntimeException;

final class FactureAssuranceNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Facture assurance #%d not found.', $id));
    }
}

<?php

namespace App\Billing\Domain\Exception;

use RuntimeException;

final class PaiementNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Paiement #%d not found.', $id));
    }
}

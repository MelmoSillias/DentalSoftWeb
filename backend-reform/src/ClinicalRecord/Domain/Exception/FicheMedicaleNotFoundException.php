<?php

namespace App\ClinicalRecord\Domain\Exception;

use RuntimeException;

final class FicheMedicaleNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Fiche medicale #%d not found.', $id));
    }
}

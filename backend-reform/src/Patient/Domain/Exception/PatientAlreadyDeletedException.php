<?php

namespace App\Patient\Domain\Exception;

use RuntimeException;

final class PatientAlreadyDeletedException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Patient #%d is already deleted.', $id));
    }
}

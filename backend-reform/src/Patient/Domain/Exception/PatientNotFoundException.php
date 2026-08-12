<?php

namespace App\Patient\Domain\Exception;

use RuntimeException;

final class PatientNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Patient #%d not found.', $id));
    }

    public static function notInTrash(int $id): self
    {
        return new self(sprintf('Patient #%d not found in trash.', $id));
    }
}

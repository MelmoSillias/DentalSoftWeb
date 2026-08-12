<?php

namespace App\Scheduling\Domain\Exception;

use RuntimeException;

final class RdvNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Rdv #%d not found.', $id));
    }
}

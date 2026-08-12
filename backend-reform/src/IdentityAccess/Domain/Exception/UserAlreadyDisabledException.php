<?php

namespace App\IdentityAccess\Domain\Exception;

use RuntimeException;

final class UserAlreadyDisabledException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('User #%d is already disabled.', $id));
    }
}

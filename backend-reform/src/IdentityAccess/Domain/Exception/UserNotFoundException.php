<?php

namespace App\IdentityAccess\Domain\Exception;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('User #%d not found.', $id));
    }
}

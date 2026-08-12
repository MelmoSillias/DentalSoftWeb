<?php

namespace App\Settings\Domain\Exception;

use RuntimeException;

final class SettingNotFoundException extends RuntimeException
{
    public static function withKey(string $key): self
    {
        return new self(sprintf('Setting "%s" not found.', $key));
    }
}

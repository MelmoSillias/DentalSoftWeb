<?php

namespace App\Settings\Domain\Exception;

use RuntimeException;

final class AppSettingNotFoundException extends RuntimeException
{
    public static function withKey(string $key): self
    {
        return new self(sprintf('App setting "%s" not found.', $key));
    }
}

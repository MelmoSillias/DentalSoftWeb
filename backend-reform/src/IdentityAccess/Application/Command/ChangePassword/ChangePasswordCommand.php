<?php

namespace App\IdentityAccess\Application\Command\ChangePassword;

final class ChangePasswordCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }
}

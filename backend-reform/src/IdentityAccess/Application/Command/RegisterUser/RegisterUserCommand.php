<?php

namespace App\IdentityAccess\Application\Command\RegisterUser;

final class RegisterUserCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }
}

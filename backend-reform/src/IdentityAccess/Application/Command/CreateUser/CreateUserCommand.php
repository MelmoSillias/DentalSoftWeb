<?php

namespace App\IdentityAccess\Application\Command\CreateUser;

final class CreateUserCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?object $actor = null,
    ) {
    }
}

<?php

namespace App\IdentityAccess\Application\Command\DeleteUser;

final class DeleteUserCommand
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

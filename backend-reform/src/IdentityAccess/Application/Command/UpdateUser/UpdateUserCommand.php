<?php

namespace App\IdentityAccess\Application\Command\UpdateUser;

final class UpdateUserCommand
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

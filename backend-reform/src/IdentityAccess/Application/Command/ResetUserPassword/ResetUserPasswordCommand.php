<?php

namespace App\IdentityAccess\Application\Command\ResetUserPassword;

final class ResetUserPasswordCommand
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

<?php

namespace App\IdentityAccess\Application\Command\SoftDisableUser;

final class SoftDisableUserCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $actorUserId = null,
    ) {
    }
}

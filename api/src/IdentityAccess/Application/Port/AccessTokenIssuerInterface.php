<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

use App\IdentityAccess\Domain\Entity\User;

interface AccessTokenIssuerInterface
{
    public function issue(User $user): string;
}

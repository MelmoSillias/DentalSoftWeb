<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\IdentityAccess\Domain\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class LexikAccessTokenIssuer implements AccessTokenIssuerInterface
{
    public function __construct(private JWTTokenManagerInterface $jwt) {}
    public function issue(User $user): string { return $this->jwt->create($user); }
}

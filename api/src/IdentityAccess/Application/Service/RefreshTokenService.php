<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Service;

use App\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\IdentityAccess\Application\Port\RefreshAccessGuardInterface;
use App\IdentityAccess\Domain\Entity\RefreshToken;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use App\IdentityAccess\Domain\Repository\RefreshTokenRepositoryInterface;

final class RefreshTokenService
{
    public function __construct(private RefreshTokenRepositoryInterface $tokens, private AccessTokenIssuerInterface $issuer, private RefreshAccessGuardInterface $guard) {}

    /** @return array{token:string,access_token:string,refresh_token:string,token_type:string} */
    public function createTokenPair(User $user, AuthRealm $realm, string $deviceIdentifier): array
    {
        if (!$user->isActive() || AuthRealm::forUser($user) !== $realm) throw new \InvalidArgumentException('Invalid session.');
        return $this->tokens->transactional(fn () => $this->issue($user, $realm, $deviceIdentifier));
    }

    public function refresh(string $plainToken, AuthRealm $realm, string $deviceIdentifier): array
    {
        return $this->tokens->transactional(function () use ($plainToken, $realm, $deviceIdentifier): array {
            $token = $this->validToken($plainToken, $realm, $deviceIdentifier);
            $this->guard->check($token->getUser());
            if (!$this->tokens->consume($token, new \DateTimeImmutable())) throw new \InvalidArgumentException('Invalid refresh token.');
            return $this->issue($token->getUser(), $realm, $deviceIdentifier);
        });
    }

    public function revoke(string $plainToken, AuthRealm $realm, string $deviceIdentifier): void
    {
        $this->tokens->transactional(function () use ($plainToken, $realm, $deviceIdentifier): void {
            $token = $this->validToken($plainToken, $realm, $deviceIdentifier);
            if (!$this->tokens->consume($token, new \DateTimeImmutable())) throw new \InvalidArgumentException('Invalid refresh token.');
        });
    }

    private function validToken(string $plainToken, AuthRealm $realm, string $deviceIdentifier): RefreshToken
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $plainToken)) throw new \InvalidArgumentException('Invalid refresh token.');
        $token = $this->tokens->findByHash(hash('sha256', $plainToken));
        if (!$token?->canRefresh($realm, hash('sha256', $deviceIdentifier), new \DateTimeImmutable())) throw new \InvalidArgumentException('Invalid refresh token.');
        return $token;
    }

    private function issue(User $user, AuthRealm $realm, string $deviceIdentifier): array
    {
        $plain = bin2hex(random_bytes(32));
        $access = $this->issuer->issue($user);
        $this->tokens->save(new RefreshToken($user, hash('sha256', $plain), $realm, hash('sha256', $deviceIdentifier), new \DateTimeImmutable('+30 days')));
        return ['token' => $access, 'access_token' => $access, 'refresh_token' => $plain, 'token_type' => 'Bearer'];
    }
}

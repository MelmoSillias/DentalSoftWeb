<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Entity;

use App\IdentityAccess\Domain\Enum\AuthRealm;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auth_refresh_token')]
#[ORM\UniqueConstraint(name: 'UNIQ_AUTH_REFRESH_HASH', columns: ['token_hash'])]
class RefreshToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(length: 16, enumType: AuthRealm::class)]
    private AuthRealm $realm;

    #[ORM\Column(length: 64)]
    private string $deviceHash;

    #[ORM\Column(length: 64)]
    private string $credentialHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(User $user, string $tokenHash, AuthRealm $realm, string $deviceHash, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->realm = $realm;
        $this->deviceHash = $deviceHash;
        $this->credentialHash = $user->getAuthenticationVersion();
        $this->expiresAt = $expiresAt;
    }

    public function getUser(): User { return $this->user; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getRealm(): AuthRealm { return $this->realm; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function revoke(\DateTimeImmutable $now): void { $this->revokedAt = $now; }

    public function canRefresh(AuthRealm $realm, string $deviceHash, \DateTimeImmutable $now): bool
    {
        return $this->revokedAt === null && $this->expiresAt > $now
            && $this->realm === $realm && hash_equals($this->deviceHash, $deviceHash)
            && $this->user->isActive() && AuthRealm::forUser($this->user) === $realm
            && hash_equals($this->credentialHash, $this->user->getAuthenticationVersion());
    }
}

<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine;

use App\IdentityAccess\Domain\Entity\RefreshToken;
use App\IdentityAccess\Domain\Repository\RefreshTokenRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findByHash(string $hash): ?RefreshToken
    {
        return $this->em->getRepository(RefreshToken::class)->findOneBy(['tokenHash' => $hash]);
    }

    public function save(RefreshToken $token): void { $this->em->persist($token); }

    public function consume(RefreshToken $token, \DateTimeImmutable $now): bool
    {
        $updated = $this->em->getConnection()->executeStatement(
            'UPDATE auth_refresh_token SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?',
            [$now, $token->getTokenHash(), $now], [Types::DATETIME_IMMUTABLE, Types::STRING, Types::DATETIME_IMMUTABLE],
        );
        if ($updated === 1) $token->revoke($now);
        return $updated === 1;
    }

    public function transactional(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(static fn () => $operation());
    }
}

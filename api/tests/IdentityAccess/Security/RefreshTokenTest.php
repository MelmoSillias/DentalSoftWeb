<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Security;

use App\IdentityAccess\Domain\Entity\RefreshToken;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    public function testBoundSessionRejectsExpiredRevokedDisabledChangedAndForeignCredentials(): void
    {
        $now = new \DateTimeImmutable();
        $user = (new User())->setUsername('doctor')->setPassword('hash-1')->setRoles(['ROLE_MEDECIN']);
        $device = hash('sha256', 'device-one');
        $token = new RefreshToken($user, hash('sha256', 'plain'), AuthRealm::App, $device, $now->modify('+1 hour'));
        self::assertTrue($token->canRefresh(AuthRealm::App, $device, $now));
        self::assertFalse($token->canRefresh(AuthRealm::Patient, $device, $now));
        self::assertFalse($token->canRefresh(AuthRealm::App, hash('sha256', 'device-two'), $now));
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now->modify('+2 hours')));
        $user->setActive(false);
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now));
        $user->setActive(true)->setPassword('hash-2');
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now));
        $user->setPassword('hash-1')->setRoles(['ROLE_PATIENT']);
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now));
        $user->setRoles(['ROLE_ADMIN']);
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now));
        $user->setRoles(['ROLE_MEDECIN']);
        $token->revoke($now);
        self::assertFalse($token->canRefresh(AuthRealm::App, $device, $now));
    }

    public function testMixedOrUnassignedAccountsHaveNoRealm(): void
    {
        self::assertNull(AuthRealm::forUser((new User())->setRoles(['ROLE_ADMIN','ROLE_PATIENT'])));
        self::assertNull(AuthRealm::forUser(new User()));
        self::assertSame(AuthRealm::Patient, AuthRealm::forUser((new User())->setRoles(['ROLE_PATIENT'])));
    }
}

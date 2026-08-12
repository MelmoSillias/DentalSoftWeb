<?php

namespace App\Tests\Unit\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\Exception\UserAlreadyDisabledException;
use App\IdentityAccess\Domain\Model\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testCreateBuildsActiveUser(): void
    {
        $user = User::create('alice', ['ROLE_ADMIN']);

        self::assertNull($user->getId());
        self::assertSame('alice', $user->getUsername());
        self::assertTrue($user->isActive());
        self::assertTrue($user->isNotificationsEnabled());
        self::assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testSoftDisableAndUpdateProfile(): void
    {
        $user = User::reconstitute(
            UserId::fromInt(1),
            'bob',
            ['ROLE_MEDECIN'],
            true,
            true,
        );

        $user->updateProfile('bobby', false);
        self::assertSame('bobby', $user->getUsername());
        self::assertFalse($user->isNotificationsEnabled());

        $user->softDisable();
        self::assertFalse($user->isActive());

        $this->expectException(UserAlreadyDisabledException::class);
        $user->softDisable();
    }

    public function testUpdateProfileRejectedWhenDisabled(): void
    {
        $user = User::reconstitute(
            UserId::fromInt(2),
            'carol',
            [],
            false,
            true,
        );

        $this->expectException(IdentityAccessDomainException::class);
        $user->updateProfile('carol2');
    }

    public function testUsernameMaxLengthInvariant(): void
    {
        $this->expectException(IdentityAccessDomainException::class);
        User::create(str_repeat('a', 181));
    }

    public function testAssertValidEmail(): void
    {
        self::assertSame('alice@example.com', User::assertValidEmail('alice@example.com'));
        self::assertNull(User::assertValidEmail(null));
        self::assertNull(User::assertValidEmail('   '));

        $this->expectException(IdentityAccessDomainException::class);
        User::assertValidEmail('not-an-email');
    }
}

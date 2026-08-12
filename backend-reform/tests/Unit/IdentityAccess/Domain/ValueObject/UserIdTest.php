<?php

namespace App\Tests\Unit\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testFromIntAcceptsPositiveValue(): void
    {
        $id = UserId::fromInt(42);

        self::assertSame(42, $id->toInt());
        self::assertTrue($id->equals(UserId::fromInt(42)));
        self::assertFalse($id->equals(UserId::fromInt(7)));
    }

    public function testFromIntRejectsNonPositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UserId::fromInt(0);
    }
}

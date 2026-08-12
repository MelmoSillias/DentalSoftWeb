<?php

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testFromStringNormalizesAndAcceptsValidEmail(): void
    {
        $email = Email::fromString('  Jane.Doe@Example.COM ');

        self::assertSame('jane.doe@example.com', $email->toString());
    }

    public function testFromStringRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }

    public function testTryFromNullableReturnsNullForEmpty(): void
    {
        self::assertNull(Email::tryFromNullable(null));
        self::assertNull(Email::tryFromNullable('   '));
    }

    public function testEquals(): void
    {
        $a = Email::fromString('a@example.com');
        $b = Email::fromString('A@example.com');
        $c = Email::fromString('b@example.com');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}

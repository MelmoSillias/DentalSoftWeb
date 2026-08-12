<?php

namespace App\Tests\Unit\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\ValueObject\EmployeId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmployeIdTest extends TestCase
{
    public function testFromIntAcceptsPositiveValue(): void
    {
        $id = EmployeId::fromInt(7);

        self::assertSame(7, $id->toInt());
        self::assertTrue($id->equals(EmployeId::fromInt(7)));
        self::assertFalse($id->equals(EmployeId::fromInt(3)));
    }

    public function testFromIntRejectsNonPositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmployeId::fromInt(0);
    }
}

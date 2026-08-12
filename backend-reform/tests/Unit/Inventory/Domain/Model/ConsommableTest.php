<?php

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Exception\InventoryDomainException;
use App\Inventory\Domain\Model\Consommable;
use App\Inventory\Domain\ValueObject\ConsommableId;
use PHPUnit\Framework\TestCase;

final class ConsommableTest extends TestCase
{
    public function testWithdrawDecreasesQuantity(): void
    {
        $consommable = Consommable::reconstitute(ConsommableId::fromInt(1), 'Gants', 10, 3);

        $consommable->withdraw(4);

        self::assertSame(6, $consommable->getQuantity());
        self::assertFalse($consommable->isLowStock());
    }

    public function testWithdrawRejectedWhenInsufficientStock(): void
    {
        $consommable = Consommable::reconstitute(ConsommableId::fromInt(2), 'Masques', 2, 1);

        $this->expectException(InventoryDomainException::class);
        $this->expectExceptionMessage('Insufficient stock.');

        $consommable->withdraw(3);
    }

    public function testIsLowStockWhenAtThreshold(): void
    {
        $consommable = Consommable::reconstitute(ConsommableId::fromInt(3), 'Compresses', 5, 5);

        self::assertTrue($consommable->isLowStock());
    }
}

<?php

namespace App\Tests\Unit\Inventory\Application\Command\CreateConsommable;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Inventory\Application\Command\CreateConsommable\CreateConsommableCommand;
use App\Inventory\Application\Command\CreateConsommable\CreateConsommableHandler;
use App\Inventory\Application\Port\InventoryWritePort;
use PHPUnit\Framework\TestCase;

final class CreateConsommableHandlerTest extends TestCase
{
    public function testDelegatesToInventoryWritePort(): void
    {
        $actor = $this->createMock(User::class);
        $data = ['nom' => 'Gants', 'quantite' => 10, 'fournisseur' => 'ACME', 'lowValue' => 2];
        $expected = ['message' => 'Consommable added successfully', 'status' => 201];

        $writePort = $this->createMock(InventoryWritePort::class);
        $writePort->expects(self::once())
            ->method('addConsommable')
            ->with($data, $actor)
            ->willReturn($expected);

        $handler = new CreateConsommableHandler($writePort);
        $result = $handler(new CreateConsommableCommand($data, $actor));

        self::assertSame($expected, $result);
    }
}

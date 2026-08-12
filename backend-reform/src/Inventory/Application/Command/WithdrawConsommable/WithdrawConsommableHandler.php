<?php

namespace App\Inventory\Application\Command\WithdrawConsommable;

use App\Inventory\Application\Port\InventoryWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class WithdrawConsommableHandler implements CommandHandler
{
    public function __construct(private readonly InventoryWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(WithdrawConsommableCommand $command): array
    {
        return $this->writePort->withdraw($command->id, $command->data, $command->actor);
    }
}

<?php

namespace App\Inventory\Application\Command\AddConsommableStock;

use App\Inventory\Application\Port\InventoryWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class AddConsommableStockHandler implements CommandHandler
{
    public function __construct(private readonly InventoryWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(AddConsommableStockCommand $command): array
    {
        return $this->writePort->addStock($command->id, $command->data, $command->actor);
    }
}

<?php

namespace App\Inventory\Application\Command\UpdateConsommable;

use App\Inventory\Application\Port\InventoryWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateConsommableHandler implements CommandHandler
{
    public function __construct(private readonly InventoryWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateConsommableCommand $command): array
    {
        return $this->writePort->editConsommable($command->id, $command->data, $command->actor);
    }
}

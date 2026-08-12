<?php

namespace App\Inventory\Application\Command\DeleteConsommable;

use App\Inventory\Application\Port\InventoryWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class DeleteConsommableHandler implements CommandHandler
{
    public function __construct(private readonly InventoryWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeleteConsommableCommand $command): array
    {
        return $this->writePort->deleteConsommable($command->id, $command->actor);
    }
}

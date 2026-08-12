<?php

namespace App\Inventory\Application\Command\CreateConsommable;

use App\Inventory\Application\Port\InventoryWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateConsommableHandler implements CommandHandler
{
    public function __construct(private readonly InventoryWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateConsommableCommand $command): array
    {
        return $this->writePort->addConsommable($command->data, $command->actor);
    }
}

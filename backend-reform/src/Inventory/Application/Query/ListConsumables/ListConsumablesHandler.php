<?php

namespace App\Inventory\Application\Query\ListConsumables;

use App\Inventory\Application\Port\InventoryReadPort;
use App\Shared\Application\Bus\QueryHandler;

final class ListConsumablesHandler implements QueryHandler
{
    public function __construct(private readonly InventoryReadPort $readPort)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListConsumablesQuery $query): array
    {
        return $this->readPort->fetchConsommables();
    }
}

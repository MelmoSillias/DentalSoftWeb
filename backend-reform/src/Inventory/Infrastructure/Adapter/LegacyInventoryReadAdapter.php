<?php

namespace App\Inventory\Infrastructure\Adapter;

use App\Inventory\Application\Port\InventoryReadPort;
use App\Inventory\Service\ConsommableService;

final class LegacyInventoryReadAdapter implements InventoryReadPort
{
    public function __construct(private readonly ConsommableService $consommableService)
    {
    }

    public function listConsumablesWithVariations(): array
    {
        return $this->consommableService->listConsumablesWithVariations();
    }

    public function fetchConsommables(): array
    {
        return $this->consommableService->fetchConsommables();
    }
}

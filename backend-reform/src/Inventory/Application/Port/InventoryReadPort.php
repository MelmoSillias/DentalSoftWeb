<?php

namespace App\Inventory\Application\Port;

interface InventoryReadPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listConsumablesWithVariations(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchConsommables(): array;
}

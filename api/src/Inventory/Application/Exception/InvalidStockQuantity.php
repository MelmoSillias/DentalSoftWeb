<?php

namespace App\Inventory\Application\Exception;

final class InvalidStockQuantity extends InventoryRejection
{
    public function __construct()
    {
        parent::__construct('Quantité invalide.');
    }
}

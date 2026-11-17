<?php

namespace App\Inventory\Application\Exception;

final class InvalidEmployee extends InventoryRejection
{
    public function __construct()
    {
        parent::__construct('Employé invalide.');
    }
}

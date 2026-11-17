<?php

namespace App\Inventory\Application\Exception;

final class UnauthenticatedInventoryActor extends InventoryRejection
{
    public function __construct()
    {
        parent::__construct('Utilisateur non authentifié');
    }

}

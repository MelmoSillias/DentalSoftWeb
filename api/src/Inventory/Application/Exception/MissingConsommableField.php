<?php

namespace App\Inventory\Application\Exception;

final class MissingConsommableField extends InventoryRejection
{
    public function __construct(string $field)
    {
        parent::__construct("Champ $field manquant");
    }
}

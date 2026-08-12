<?php

namespace App\Billing\Application\Command\ValidateDevis;

final class ValidateDevisCommand
{
    public function __construct(public readonly int $devisId)
    {
    }
}

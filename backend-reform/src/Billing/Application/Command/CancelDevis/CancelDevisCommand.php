<?php

namespace App\Billing\Application\Command\CancelDevis;

final class CancelDevisCommand
{
    public function __construct(public readonly int $devisId)
    {
    }
}

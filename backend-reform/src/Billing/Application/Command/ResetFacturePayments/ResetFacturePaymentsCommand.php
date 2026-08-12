<?php

namespace App\Billing\Application\Command\ResetFacturePayments;

final class ResetFacturePaymentsCommand
{
    public function __construct(public readonly int $factureId)
    {
    }
}

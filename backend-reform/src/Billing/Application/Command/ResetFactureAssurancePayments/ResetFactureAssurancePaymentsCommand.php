<?php

namespace App\Billing\Application\Command\ResetFactureAssurancePayments;

final class ResetFactureAssurancePaymentsCommand
{
    public function __construct(public readonly int $factureId)
    {
    }
}

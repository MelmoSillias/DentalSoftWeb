<?php

namespace App\Billing\Application\Query\GetFactureAssurancePrint;

final class GetFactureAssurancePrintQuery
{
    public function __construct(public readonly int $factureId)
    {
    }
}

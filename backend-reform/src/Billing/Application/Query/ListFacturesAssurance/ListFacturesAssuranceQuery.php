<?php

namespace App\Billing\Application\Query\ListFacturesAssurance;

use DateTimeInterface;

final class ListFacturesAssuranceQuery
{
    public function __construct(
        public readonly ?DateTimeInterface $start = null,
        public readonly ?DateTimeInterface $end = null,
        public readonly ?string $status = null,
        public readonly ?string $patientQuery = null,
        public readonly ?string $assuranceCode = null,
    ) {
    }
}

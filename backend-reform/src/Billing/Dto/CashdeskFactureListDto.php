<?php

namespace App\Billing\Dto;

final class CashdeskFactureListDto
{
    public function __construct(
        private array $factures,
        private array $facturesAssurance,
    ) {
    }

    public function getFactures(): array
    {
        return $this->factures;
    }

    public function getFacturesAssurance(): array
    {
        return $this->facturesAssurance;
    }

    public function toArray(): array
    {
        return [
            'factures' => $this->factures,
            'facturesAssurance' => $this->facturesAssurance,
            'all' => array_merge($this->factures, $this->facturesAssurance),
        ];
    }
}

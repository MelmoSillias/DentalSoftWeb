<?php

namespace App\Billing\Dto;

final class InsuranceClaimLineDto
{
    public function __construct(
        private string $lineType,
        private string $designation,
        private int $quantite,
        private float $montant,
        private float $total,
        private ?string $description = null,
        private bool $includedInInvoiceTotals = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'lineType' => $this->lineType,
            'designation' => $this->designation,
            'description' => $this->description,
            'quantite' => $this->quantite,
            'montant' => $this->montant,
            'total' => $this->total,
            'includedInInvoiceTotals' => $this->includedInInvoiceTotals,
        ];
    }
}

<?php

namespace App\Dto\Focus;

final class FocusReceptionInvoiceLineDto
{
    public function __construct(
        private int $id,
        private string $label,
        private int $quantity,
        private float $unitPrice,
        private float $total,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'total' => $this->total,
        ];
    }
}
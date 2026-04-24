<?php

namespace App\Dto\Focus;

final class FocusReceptionBillingDto
{
    /** @param FocusReceptionInvoiceLineDto[] $lines */
    /** @param FocusReceptionPaymentDto[] $payments */
    public function __construct(
        private int $invoiceId,
        private float $total,
        private float $remaining,
        private array $state,
        private array $lines,
        private array $payments,
    ) {
    }

    public function toArray(): array
    {
        return [
            'invoiceId' => $this->invoiceId,
            'total' => $this->total,
            'remaining' => $this->remaining,
            'state' => $this->state,
            'lines' => array_map(static fn (FocusReceptionInvoiceLineDto $line) => $line->toArray(), $this->lines),
            'payments' => array_map(static fn (FocusReceptionPaymentDto $payment) => $payment->toArray(), $this->payments),
        ];
    }
}
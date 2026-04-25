<?php

namespace App\Dto\Focus;

final class FocusReceptionPaymentDto
{
    public function __construct(
        private int $id,
        private float $montant,
        private ?string $mode,
        private ?string $date,
        private string $rolePaiement,
        private string $type,
        private string $status,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'montant' => $this->montant,
            'mode' => $this->mode,
            'date' => $this->date,
            'rolePaiement' => $this->rolePaiement,
            'type' => $this->type,
            'status' => $this->status,
        ];
    }
}
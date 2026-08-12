<?php

namespace App\Billing\Application\Command\CreateTransaction;

use DateTimeInterface;

final class CreateTransactionCommand
{
    public function __construct(
        public readonly string $type,
        public readonly float $montant,
        public readonly ?string $description,
        public readonly DateTimeInterface $date,
        public readonly int $modeId,
        public readonly ?string $motif = null,
    ) {
    }
}

<?php

namespace App\Billing\Application\Command\TransferInterCompte;

use DateTimeInterface;

final class TransferInterCompteCommand
{
    public function __construct(
        public readonly int $fromId,
        public readonly int $toId,
        public readonly float $montant,
        public readonly string $motif,
        public readonly DateTimeInterface $date,
    ) {
    }
}

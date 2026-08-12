<?php

namespace App\Billing\Application\Command\CreateDevis;

final class CreateDevisCommand
{
    /**
     * Create/upsert devis for a fiche via legacy payload (same shape as ClinicalRecord updateDevis).
     *
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly array $payload,
    ) {
    }
}

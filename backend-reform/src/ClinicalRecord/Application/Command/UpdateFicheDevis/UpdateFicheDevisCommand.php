<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheDevis;

final class UpdateFicheDevisCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly array $payload,
    ) {
    }
}

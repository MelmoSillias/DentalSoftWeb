<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheEntretien;

final class UpdateFicheEntretienCommand
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

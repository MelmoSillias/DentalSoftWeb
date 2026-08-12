<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheExamens;

final class UpdateFicheExamensCommand
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

<?php

namespace App\ClinicalRecord\Application\Command\UpdateFichePlanTraitement;

final class UpdateFichePlanTraitementCommand
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

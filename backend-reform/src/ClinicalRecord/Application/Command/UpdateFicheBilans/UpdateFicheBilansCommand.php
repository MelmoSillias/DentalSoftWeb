<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheBilans;

final class UpdateFicheBilansCommand
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

<?php

namespace App\ClinicalRecord\Application\Command\UpdateFicheDocuments;

final class UpdateFicheDocumentsCommand
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int|string, mixed> $files
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly array $payload,
        public readonly array $files = [],
    ) {
    }
}

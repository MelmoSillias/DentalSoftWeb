<?php

namespace App\CareDelivery\Application\Command\UpdateFactureLines;

final class UpdateFactureLinesCommand
{
    /**
     * @param list<array<string, mixed>> $lignes
     */
    public function __construct(
        public readonly int $consultationId,
        public readonly array $lignes,
        public readonly ?string $date = null,
        public readonly ?string $time = null,
    ) {
    }
}

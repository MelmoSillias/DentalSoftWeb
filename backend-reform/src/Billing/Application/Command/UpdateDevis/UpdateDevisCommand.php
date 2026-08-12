<?php

namespace App\Billing\Application\Command\UpdateDevis;

final class UpdateDevisCommand
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

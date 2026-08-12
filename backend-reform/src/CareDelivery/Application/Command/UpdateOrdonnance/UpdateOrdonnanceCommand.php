<?php

namespace App\CareDelivery\Application\Command\UpdateOrdonnance;

final class UpdateOrdonnanceCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $ordonnanceId,
        public readonly array $payload,
    ) {
    }
}

<?php

namespace App\Billing\Application\Command\PayFacture;

final class PayFactureCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $factureId,
        public readonly array $payload = [],
    ) {
    }
}

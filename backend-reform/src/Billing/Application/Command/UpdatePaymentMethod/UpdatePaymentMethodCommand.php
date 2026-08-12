<?php

namespace App\Billing\Application\Command\UpdatePaymentMethod;

final class UpdatePaymentMethodCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $id,
        public readonly array $data,
    ) {
    }
}

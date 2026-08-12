<?php

namespace App\Billing\Application\Command\CreatePaymentMethod;

final class CreatePaymentMethodCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }
}

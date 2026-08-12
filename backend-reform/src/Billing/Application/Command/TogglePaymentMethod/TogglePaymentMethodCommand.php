<?php

namespace App\Billing\Application\Command\TogglePaymentMethod;

final class TogglePaymentMethodCommand
{
    public function __construct(public readonly int $id)
    {
    }
}

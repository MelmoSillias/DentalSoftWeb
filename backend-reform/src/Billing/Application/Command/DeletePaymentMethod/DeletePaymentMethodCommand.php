<?php

namespace App\Billing\Application\Command\DeletePaymentMethod;

final class DeletePaymentMethodCommand
{
    public function __construct(public readonly int $id)
    {
    }
}

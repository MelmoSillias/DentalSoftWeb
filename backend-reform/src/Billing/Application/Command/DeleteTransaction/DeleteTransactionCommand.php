<?php

namespace App\Billing\Application\Command\DeleteTransaction;

final class DeleteTransactionCommand
{
    public function __construct(public readonly int $transactionId)
    {
    }
}

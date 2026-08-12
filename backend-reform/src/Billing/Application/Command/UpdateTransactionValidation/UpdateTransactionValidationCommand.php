<?php

namespace App\Billing\Application\Command\UpdateTransactionValidation;

use DateTimeImmutable;

final class UpdateTransactionValidationCommand
{
    public function __construct(
        public readonly int $transactionId,
        public readonly string $status,
        public readonly ?string $comment = null,
        public readonly ?DateTimeImmutable $validatedAt = null,
    ) {
    }
}

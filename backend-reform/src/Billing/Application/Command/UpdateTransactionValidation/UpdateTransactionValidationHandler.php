<?php

namespace App\Billing\Application\Command\UpdateTransactionValidation;

use App\Billing\Application\Port\BillingWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateTransactionValidationHandler implements CommandHandler
{
    public function __construct(private readonly BillingWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateTransactionValidationCommand $command): array
    {
        return $this->writePort->updateTransactionValidationStatus(
            $command->transactionId,
            $command->status,
            $command->comment,
            $command->validatedAt,
        );
    }
}

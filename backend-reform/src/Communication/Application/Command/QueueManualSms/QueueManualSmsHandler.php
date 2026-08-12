<?php

namespace App\Communication\Application\Command\QueueManualSms;

use App\Communication\Application\Port\SmsWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class QueueManualSmsHandler implements CommandHandler
{
    public function __construct(private readonly SmsWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(QueueManualSmsCommand $command): array
    {
        return $this->writePort->queueManual(
            $command->phone,
            $command->message,
            $command->patientId,
            $command->type,
            $command->source,
            $command->sendAt,
            $command->metadata,
        );
    }
}
